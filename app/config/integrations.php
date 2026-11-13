<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

$environment = [
    'frontend_url' => 'FRONTEND_URL',
    'paymongo_secret_key' => 'PAYMONGO_SECRET_KEY',
    'paymongo_webhook_secret' => 'PAYMONGO_WEBHOOK_SECRET',
    'paymongo_nearby_deposit_amount' => 'PAYMONGO_NEARBY_DEPOSIT_AMOUNT',
    'gemini_api_key' => 'GEMINI_API_KEY',
    'gemini_model' => 'GEMINI_MODEL',
    'gemini_fallback_models' => 'GEMINI_FALLBACK_MODELS',
    'osm_overpass_url' => 'OSM_OVERPASS_URL',
    'osm_overpass_fallback_urls' => 'OSM_OVERPASS_FALLBACK_URLS',
    'mail_host' => 'MAIL_HOST',
    'mail_port' => 'MAIL_PORT',
    'mail_username' => 'MAIL_USERNAME',
    'mail_password' => 'MAIL_PASSWORD',
    'mail_encryption' => 'MAIL_ENCRYPTION',
    'mail_from_address' => 'MAIL_FROM_ADDRESS',
    'mail_from_name' => 'MAIL_FROM_NAME',
    'reminder_cron_token' => 'REMINDER_CRON_TOKEN',
    'google_client_id' => 'GOOGLE_CLIENT_ID',
    'google_client_secret' => 'GOOGLE_CLIENT_SECRET',
    'google_redirect_uri' => 'GOOGLE_REDIRECT_URI',
    'sentry_dsn' => 'SENTRY_DSN',
    'sentry_environment' => 'SENTRY_ENVIRONMENT',
    'journal_storage_driver' => 'JOURNAL_STORAGE_DRIVER',
    'refresh_cookie_samesite' => 'REFRESH_COOKIE_SAMESITE',
    'refresh_cookie_secure' => 'REFRESH_COOKIE_SECURE',
    'refresh_cookie_domain' => 'REFRESH_COOKIE_DOMAIN',
];

foreach ($environment as $key => $variable) {
    $value = getenv($variable);
    $config[$key] = $value === false ? '' : $value;
}

unset($environment, $key, $variable, $value);
