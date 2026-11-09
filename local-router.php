<?php

$public_root = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'public');
$request_path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requested_file = realpath(
    $public_root . DIRECTORY_SEPARATOR . ltrim(rawurldecode($request_path), '/\\')
);

if (
    $requested_file !== false
    && is_file($requested_file)
    && strpos($requested_file, $public_root . DIRECTORY_SEPARATOR) === 0
    && strtolower(pathinfo($requested_file, PATHINFO_EXTENSION)) !== 'php'
) {
    return false;
}

require $public_root . DIRECTORY_SEPARATOR . 'index.php';
