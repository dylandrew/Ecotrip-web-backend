<?php

class ExpirePendingBookings {
    public static $command = 'bookings:expire';
    public static $description = 'Mark pending bookings older than 24 hours as failed';
    public static $arguments = [];

    public function handle($input = null, array $flags = []) {
        $root = dirname(__DIR__, 2);
        $environment = parse_ini_file($root . DIRECTORY_SEPARATOR . '.env', false, INI_SCANNER_RAW) ?: [];
        foreach ($environment as $key => $value) putenv($key . '=' . $value);

        require_once $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'integration_helper.php';
        require_once $root . DIRECTORY_SEPARATOR . 'scheme' . DIRECTORY_SEPARATOR . 'kernel' . DIRECTORY_SEPARATOR . 'Routine.php';
        require_once $root . DIRECTORY_SEPARATOR . 'scheme' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'Database.php';

        $db = new Database();
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $pending = $db->table('bookings')
            ->where('payment_status', 'pending')
            ->get_all();
        $expired = array_values(array_filter($pending ?: [], static function ($booking) use ($cutoff) {
            return strcmp((string) ($booking['created_at'] ?? ''), $cutoff) <= 0;
        }));

        foreach ($expired as $booking) {
            $db->table('bookings')->where('id', (int) $booking['id'])->where('payment_status', 'pending')->update(['payment_status' => 'failed']);
            integration_queue_booking_status_email($db, (int) $booking['id'], 'failed');
        }
        echo count($expired) . ' pending booking(s) expired.' . PHP_EOL;
    }
}
