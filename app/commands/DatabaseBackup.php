<?php

class DatabaseBackup {
    public static $command = 'database:backup';
    public static $description = 'Create a private EcoTrip database backup';
    public static $arguments = [];

    public function handle($input = null, array $flags = []) {
        $root = dirname(__DIR__, 2);
        $environment = parse_ini_file($root . DIRECTORY_SEPARATOR . '.env', false, INI_SCANNER_RAW) ?: [];
        foreach (['DB_DRIVER', 'DB_PATH', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_CHARSET', 'DB_USER', 'DB_PASSWORD', 'ECOTRIP_BACKUP_DIR'] as $key) {
            $process_value = getenv($key);
            if ($process_value !== false) $environment[$key] = $process_value;
        }
        foreach ($environment as $key => $value) {
            if (getenv($key) === false) putenv($key . '=' . $value);
        }

        $driver = strtolower(trim((string) ($environment['DB_DRIVER'] ?? '')));
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('DB_DRIVER must be set to mysql or sqlite before creating a backup.');
        }

        $backup_dir = trim((string) ($environment['ECOTRIP_BACKUP_DIR'] ?? ''));
        if ($backup_dir === '') $backup_dir = $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'backups';
        if (!is_dir($backup_dir) && !mkdir($backup_dir, 0700, true) && !is_dir($backup_dir)) {
            throw new RuntimeException('Could not create the configured backup directory.');
        }
        $backup_dir = realpath($backup_dir);
        $public_dir = realpath($root . DIRECTORY_SEPARATOR . 'public');
        if (!$backup_dir || !$public_dir || $this->is_inside($backup_dir, $public_dir)) {
            throw new RuntimeException('The backup directory must be outside the public directory.');
        }

        $timestamp = date('Ymd_His');
        if ($driver === 'sqlite') {
            $database_path = trim((string) ($environment['DB_PATH'] ?? ''));
            if ($database_path === '' || !is_file($database_path)) {
                throw new RuntimeException('DB_PATH must point to the existing SQLite database file.');
            }
            $destination = $backup_dir . DIRECTORY_SEPARATOR . 'ecotrip_' . $timestamp . '.sqlite';
            $pdo = new PDO('sqlite:' . $database_path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('VACUUM INTO ' . $pdo->quote($destination));
        } else {
            $destination = $backup_dir . DIRECTORY_SEPARATOR . 'ecotrip_' . $timestamp . '.sql';
            $dsn = 'mysql:host=' . ($environment['DB_HOST'] ?? '')
                . ';port=' . ($environment['DB_PORT'] ?? '3306')
                . ';dbname=' . ($environment['DB_NAME'] ?? '')
                . ';charset=' . ($environment['DB_CHARSET'] ?? 'utf8mb4');
            $pdo = new PDO(
                $dsn,
                (string) ($environment['DB_USER'] ?? ''),
                (string) ($environment['DB_PASSWORD'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $this->write_mysql_backup($pdo, $destination);
        }

        if (!is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('The backup command did not create a non-empty file.');
        }
        if (!chmod($destination, 0600)) {
            if (!unlink($destination)) error_log('A backup with unverified file permissions could not be removed.');
            throw new RuntimeException('Could not restrict access to the backup file.');
        }
        echo 'Database backup created: ' . $destination . PHP_EOL;
    }

    private function write_mysql_backup(PDO $pdo, $destination) {
        $handle = fopen($destination, 'xb');
        if (!$handle) throw new RuntimeException('Could not create the backup file.');

        try {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            $this->write_line($handle, "-- EcoTrip MySQL backup created " . date(DATE_ATOM) . PHP_EOL);
            $this->write_line($handle, "SET FOREIGN_KEY_CHECKS=0;" . PHP_EOL);
            foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                $quoted_table = '`' . str_replace('`', '``', $table) . '`';
                $definition = $pdo->query('SHOW CREATE TABLE ' . $quoted_table)->fetch(PDO::FETCH_NUM);
                if (!$definition || empty($definition[1])) {
                    throw new RuntimeException('Could not read a table definition for the backup.');
                }
                $this->write_line($handle, PHP_EOL . 'DROP TABLE IF EXISTS ' . $quoted_table . ';' . PHP_EOL);
                $this->write_line($handle, $definition[1] . ';' . PHP_EOL);
                $rows = $pdo->query('SELECT * FROM ' . $quoted_table);
                while ($row = $rows->fetch(PDO::FETCH_NUM)) {
                    $values = array_map(static function ($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote((string) $value);
                    }, $row);
                    $this->write_line($handle, 'INSERT INTO ' . $quoted_table . ' VALUES (' . implode(', ', $values) . ');' . PHP_EOL);
                }
            }
            $this->write_line($handle, PHP_EOL . 'SET FOREIGN_KEY_CHECKS=1;' . PHP_EOL);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fclose($handle);
            if (file_exists($destination) && !unlink($destination)) {
                error_log('An incomplete database backup could not be removed.');
            }
            throw $exception;
        }
        if (!fclose($handle)) {
            if (!unlink($destination)) error_log('A backup that failed to close could not be removed.');
            throw new RuntimeException('Could not close the completed backup file.');
        }
    }

    private function write_line($handle, $content) {
        if (fwrite($handle, $content) !== strlen($content)) {
            throw new RuntimeException('Could not write the complete database backup.');
        }
    }

    private function is_inside($candidate, $parent) {
        $candidate = strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR));
        $parent = strtolower(rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $parent), DIRECTORY_SEPARATOR));
        return $candidate === $parent || str_starts_with($candidate, $parent . DIRECTORY_SEPARATOR);
    }
}
