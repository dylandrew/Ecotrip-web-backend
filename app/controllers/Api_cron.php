<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_cron extends Api_v1 {
    public function send_reminders() {
        $secret = trim((string) config_item('reminder_cron_token'));
        if (strlen($secret) < 32) {
            api_json_response(503, null, 'REMINDER_CRON_NOT_CONFIGURED', 'Reminder cron authentication is not configured.');
        }

        if (!hash_equals($secret, api_bearer_token())) {
            api_json_response(401, null, 'UNAUTHORIZED', 'A valid cron bearer token is required.');
        }

        $root = dirname(__DIR__, 2);
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'commands' . DIRECTORY_SEPARATOR . 'SendDueReminders.php';
            (new SendDueReminders())->handle();
            $output = trim((string) ob_get_clean());
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            error_log('Reminder cron endpoint failed: ' . $exception->getMessage());
            api_json_response(500, null, 'REMINDER_DELIVERY_FAILED', 'Reminder delivery failed. Check the server logs.');
        }

        api_json_response(200, ['status' => 'completed', 'output' => $output]);
    }
}
