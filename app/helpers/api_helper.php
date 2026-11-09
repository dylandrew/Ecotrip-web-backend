<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

function api_json_response($status, $data = null, $code = null, $message = null) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    if ($status >= 400 || $code !== null) {
        echo json_encode(['data' => null, 'error' => ['code' => $code ?: 'HTTP_ERROR', 'message' => $message ?: 'Request failed']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['data' => $data, 'error' => null], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function api_audit_log($db, $user_id, $action, $target_type = null, $target_id = null) {
    $db->table('audit_logs')->insert([
        'user_id' => $user_id === null ? null : (int) $user_id,
        'action' => mb_substr((string) $action, 0, 100),
        'target_type' => $target_type === null ? null : mb_substr((string) $target_type, 0, 50),
        'target_id' => $target_id === null ? null : (int) $target_id,
        'ip' => integration_client_ip(),
    ]);
}

function api_json_body() {
    $input = lava_instance()->request->json(true);
    return is_array($input) ? $input : [];
}

function api_bearer_token() {
    $request = lava_instance()->request;
    $header = $request->header('Authorization') ?? $request->server('REDIRECT_HTTP_AUTHORIZATION') ?? '';
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) if (strcasecmp($name, 'Authorization') === 0) { $header = $value; break; }
    }
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) return '';
    return trim($matches[1]);
}

function api_cors_headers() {
    $origins = config_item('allow_origin') ?: [];
    if (in_array(strtolower((string) (config_item('environment') ?: 'development')), ['development', 'testing'], true)
        && !in_array('http://localhost:5173', $origins, true)) {
        $origins[] = 'http://localhost:5173';
    }
    $origin = trim((string) lava_instance()->request->header('Origin'));
    if ($origin !== '' && in_array($origin, $origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Max-Age: 3600');
}

function api_refresh_cookie($token, $clear = false) {
    $requestedSameSite = strtolower(trim((string) (config_item('refresh_cookie_samesite') ?: 'Lax')));
    $sameSite = $requestedSameSite === 'none' ? 'None' : 'Lax';
    $secureSetting = filter_var(config_item('refresh_cookie_secure') ?: false, FILTER_VALIDATE_BOOLEAN);
    $secure = $sameSite === 'None' || $secureSetting || str_starts_with(strtolower((string) (config_item('base_url') ?: '')), 'https://');
    $opts = ['expires' => $clear ? time() - 3600 : time() + 604800, 'path' => '/api/v1/auth', 'domain' => trim((string) (config_item('refresh_cookie_domain') ?: '')), 'secure' => $secure, 'httponly' => true, 'samesite' => $sameSite];
    if ($opts['domain'] === '') unset($opts['domain']);
    setcookie('refresh_token', $clear ? '' : $token, $opts);
}

function api_storage_store_upload($trip_file) {
    $driver = strtolower(trim((string) (config_item('journal_storage_driver') ?: 'local')));
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $driver)) return ['error' => 'The configured journal storage driver is invalid.'];
    $handler = 'api_storage_' . $driver . '_store_upload';
    if (!function_exists($handler)) return ['error' => 'The configured journal storage driver is not available.'];
    return $handler($trip_file);
}

function api_storage_local_store_upload($trip_file) {
    if (($trip_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['error' => 'Photo upload failed.'];
    if (($trip_file['size'] ?? 0) > 8 * 1024 * 1024 || !is_uploaded_file($trip_file['tmp_name'])) return ['error' => 'Choose a photo no larger than 8 MB.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($trip_file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($trip_file['tmp_name']) === false || !extension_loaded('gd')) return ['error' => 'Use a valid JPEG, PNG, or WebP image.'];
    $image = match ($mime) {'image/jpeg' => @imagecreatefromjpeg($trip_file['tmp_name']), 'image/png' => @imagecreatefrompng($trip_file['tmp_name']), 'image/webp' => @imagecreatefromwebp($trip_file['tmp_name'])};
    if (!$image) return ['error' => 'The photo could not be processed.'];
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $directory = ROOT_DIR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'journal';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) return ['error' => 'Photo storage is unavailable.'];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    $saved = match ($mime) {'image/jpeg' => imagejpeg($image, $destination, 85), 'image/png' => imagepng($image, $destination, 9), 'image/webp' => imagewebp($image, $destination, 85)};
    imagedestroy($image);
    if (!$saved) return ['error' => 'The photo could not be saved.'];
    $relative = 'uploads/journal/' . $filename;
    $base = rtrim((string) (config_item('base_url') ?: 'http://127.0.0.1:8080'), '/');
    return ['relative_path' => $relative, 'url' => $base . '/' . $relative];
}

function api_json_redirect_error($error) {
    $front = rtrim((string) (config_item('frontend_url') ?: 'http://localhost:5173'), '/');
    header('Location: ' . $front . '/auth/google/callback?error=' . rawurlencode($error), true, 302);
    exit;
}

function api_storage_delete_upload($relative_path) {
    $driver = strtolower(trim((string) (config_item('journal_storage_driver') ?: 'local')));
    if (!preg_match('/^[a-z][a-z0-9_]*$/', $driver)) return false;
    $handler = 'api_storage_' . $driver . '_delete_upload';
    return function_exists($handler) && $handler($relative_path);
}

function api_storage_local_delete_upload($relative_path) {
    $relative = ltrim(str_replace('\\', '/', $relative_path), '/');
    if (!str_starts_with($relative, 'uploads/journal/') || !preg_match('/^[a-zA-Z0-9._\/]+$/', $relative)) return false;
    $path = ROOT_DIR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_file($path) ? @unlink($path) : false;
}
