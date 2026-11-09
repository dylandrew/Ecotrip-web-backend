<?php

class SendDueReminders {
    public static $command = 'reminders:send';
    public static $description = 'Send due trip reminders and booking notifications by email';
    public static $arguments = [];

    public function handle($input = null, array $flags = []) {
        $root = dirname(__DIR__, 2);
        $environmentFile = $root . DIRECTORY_SEPARATOR . '.env';
        if (is_file($environmentFile)) {
            $environment = parse_ini_file($environmentFile, false, INI_SCANNER_RAW);
            if ($environment === false) {
                throw new RuntimeException('The backend .env file could not be parsed.');
            }
            foreach ($environment as $key => $value) {
                if (getenv($key) === false) putenv($key . '=' . $value);
            }
        }

        require_once $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!defined('ROOT_DIR')) define('ROOT_DIR', $root . DIRECTORY_SEPARATOR);
        require_once $root . DIRECTORY_SEPARATOR . 'scheme' . DIRECTORY_SEPARATOR . 'kernel' . DIRECTORY_SEPARATOR . 'Routine.php';

        $config = [];
        require $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'integrations.php';
        $config = get_config($config);
        date_default_timezone_set((string) ($config['date_default_timezone'] ?? 'Asia/Manila'));

        require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'integration_helper.php';
        require_once $root . DIRECTORY_SEPARATOR . 'scheme' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'Database.php';

        $db = new Database();
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $upcoming = $db->table('trips t')
            ->select('t.id, t.title, t.destination, t.start_date, t.end_date, u.name AS traveler_name, u.email')
            ->join('users u', 'u.id = t.user_id')
            ->where('t.start_date', $tomorrow)
            ->limit(500)
            ->get_all();
        foreach ($upcoming as $trip) {
            if (integration_queue_trip_email($db, 'trip:' . (int) $trip['id'] . ':start-1-day', $trip, 'Your EcoTrip starts tomorrow', 'Your trip starts tomorrow. Here is your itinerary to help you get ready.')) {
                echo 'Queued one-day itinerary email for trip #' . (int) $trip['id'] . '.' . PHP_EOL;
            }
        }

        $due = $db->table('reminders r')
            ->select('r.id, r.trip_id, r.message, t.title, t.destination, t.start_date, t.end_date, t.user_id, u.name AS traveler_name, u.email')
            ->join('trips t', 't.id = r.trip_id')
            ->join('users u', 'u.id = t.user_id')
            ->where('r.status', 'pending')
            ->where('r.send_at', '<=', $now)
            ->order_by('r.send_at')
            ->limit(100)
            ->get_all();
        foreach ($due as $reminder) {
            integration_queue_trip_email($db, 'reminder:' . (int) $reminder['id'], $reminder, 'Your EcoTrip trip reminder', $reminder['message']);
        }

        if (integration_send_email_is_configured()) {
            $failedReminderEmails = $db->table('email_notifications')
                ->select('event_key')
                ->where('status', 'failed')
                ->get_all();
            foreach ($failedReminderEmails as $failedEmail) {
                if (!preg_match('/^reminder:(\d+)$/', $failedEmail['event_key'], $matches)) continue;
                $reminderId = (int) $matches[1];
                $reminder = $db->table('reminders')
                    ->select('id')
                    ->where('id', $reminderId)
                    ->where('status', 'pending')
                    ->limit(1)
                    ->get();
                if (!$reminder) continue;

                $db->table('email_notifications')
                    ->where('event_key', $failedEmail['event_key'])
                    ->where('status', 'failed')
                    ->update([
                        'status' => 'pending',
                        'attempts' => 0,
                        'next_attempt_at' => $now,
                        'last_error' => null,
                    ]);
                echo 'Re-queued reminder:' . $reminderId . ' after previous delivery failures.' . PHP_EOL;
            }
        }

        $notifications = $db->table('email_notifications')
            ->select('event_key, recipient, subject, body, attempts')
            ->where('status', 'pending')
            ->where('next_attempt_at', '<=', $now)
            ->order_by('created_at')
            ->limit(100)
            ->get_all();
        if ($notifications && integration_send_email_is_configured()) {
            $failedDeliveries = 0;
            foreach ($notifications as $notification) {
                if (integration_send_email($notification['recipient'], $notification['subject'], $notification['body'])) {
                    $db->table('email_notifications')->where('event_key', $notification['event_key'])->where('status', 'pending')->update(['status' => 'sent', 'sent_at' => $now, 'last_error' => null]);
                    if (preg_match('/^reminder:(\d+)$/', $notification['event_key'], $matches)) {
                        $db->table('reminders')->where('id', (int) $matches[1])->where('status', 'pending')->update(['status' => 'sent']);
                    }
                    echo 'Email sent for ' . $notification['event_key'] . '.' . PHP_EOL;
                    continue;
                }

                $attempts = (int) $notification['attempts'] + 1;
                $smtp_error = trim((string) ($GLOBALS['integration_last_smtp_error'] ?? 'SMTP delivery failed.'));
                $db->table('email_notifications')->where('event_key', $notification['event_key'])->where('status', 'pending')->update([
                    'attempts' => $attempts,
                    'next_attempt_at' => date('Y-m-d H:i:s', time() + min(86400, 300 * (2 ** min($attempts, 8)))),
                    'status' => $attempts >= 8 ? 'failed' : 'pending',
                    'last_error' => substr($smtp_error, 0, 255),
                ]);
                echo 'Email delivery failed for ' . $notification['event_key'] . '.' . PHP_EOL;
                $failedDeliveries++;
            }
            if ($failedDeliveries > 0) {
                throw new RuntimeException($failedDeliveries . ' queued email(s) could not be delivered; see SMTP errors above.');
            }
        } elseif ($notifications) {
            echo 'SMTP is missing or invalid; queued emails were left pending. Check MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, and MAIL_FROM_ADDRESS.' . PHP_EOL;
            throw new RuntimeException('SMTP configuration is required to send queued reminders.');
        }

        if (!$due && !$upcoming && !$notifications) echo 'No due reminders or queued email messages.' . PHP_EOL;
    }
}
