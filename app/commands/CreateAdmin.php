<?php

class CreateAdmin {
    public static $command = 'admin:create';
    public static $description = 'Create the first EcoTrip administrator account';
    public static $arguments = [];

    public function handle($input = null, array $flags = []) {
        $root = dirname(__DIR__, 2);
        $environment = parse_ini_file($root . DIRECTORY_SEPARATOR . '.env', false, INI_SCANNER_RAW) ?: [];
        foreach ($environment as $key => $value) putenv($key . '=' . $value);
        if (!defined('PREVENT_DIRECT_ACCESS')) define('PREVENT_DIRECT_ACCESS', true);
        if (!defined('ROOT_DIR')) define('ROOT_DIR', $root . DIRECTORY_SEPARATOR);
        require_once ROOT_DIR . 'scheme' . DIRECTORY_SEPARATOR . 'kernel' . DIRECTORY_SEPARATOR . 'Routine.php';
        require_once ROOT_DIR . 'scheme' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'Database.php';
        $db = new Database();
        if ((int) $db->table('users')->where('role', 'admin')->count() > 0) {
            echo "An administrator already exists. Sign in and create additional accounts from Admin → Users." . PHP_EOL;
            return;
        }

        $read = static function ($prompt) {
            fwrite(STDOUT, $prompt);
            return trim((string) fgets(STDIN));
        };
        $read_password = static function () {
            $script = '$secret = Read-Host -Prompt "Admin password (12+ characters)" -AsSecureString; $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret); try { [Console]::WriteLine([Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes([Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)))) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }';
            $process = proc_open(['powershell.exe', '-NoLogo', '-NoProfile', '-Command', $script], [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
            if (!is_resource($process)) return '';
            $encoded = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $status = proc_close($process);
            if ($status !== 0) return '';
            $password = base64_decode(trim((string) $encoded), true);
            return is_string($password) ? $password : '';
        };
        $name = $read('Admin name: ');
        $email = strtolower($read('Admin email: '));
        $new_password = $read_password();
        if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || strlen($new_password) < 12) {
            fwrite(STDERR, "Invalid name, email, or password. Nothing was created." . PHP_EOL);
            exit(1);
        }

        $db->table('users')->insert([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($new_password, PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
        echo "Administrator created. Sign in at /login; the password was not saved to this output." . PHP_EOL;
    }
}