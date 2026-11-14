<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

function integration_initialize_sentry() {
    $app_env = strtolower(trim((string) (config_item('environment') ?: 'development')));
    $paymongo_secret = trim((string) config_item('paymongo_secret_key'));
    if ($app_env === 'production' && str_starts_with($paymongo_secret, 'sk_test_')) {
        throw new RuntimeException('PayMongo test keys are not allowed in production.');
    }

    $dsn = trim((string) config_item('sentry_dsn'));
    if ($dsn === '' || !class_exists(\Sentry\SentrySdk::class)) return;

    \Sentry\init([
        'dsn' => $dsn,
        'environment' => config_item('sentry_environment') ?: (config_item('environment') ?: 'development'),
        'send_default_pii' => false,
        'before_send' => static function (\Sentry\Event $event) {
            $event->setRequest([]);
            $event->setUser(null);
            return $event;
        },
    ]);
}

integration_initialize_sentry();

function integration_require_csrf() {
    $field = config_item('csrf_token_name');
    $cookie = (config_item('cookie_prefix') ?: '') . config_item('csrf_cookie_name');
    $request = lava_instance()->request;
    $posted = $request->post($field);
    $stored = $request->cookie($cookie);
    if (!is_string($posted) || !is_string($stored) || $posted === '' || !hash_equals($stored, $posted)) {
        $request = lava_instance()->request;
        $requested_with = strtolower((string) $request->header('X-Requested-With'));
        $accept = strtolower((string) $request->header('Accept'));
        $is_ajax = $requested_with === 'xmlhttprequest' || str_contains($accept, 'application/json');

        if ($is_ajax) {
            integration_json_response(403, ['error' => 'Your session token expired. Reload the page and try again.']);
        }

        show_error(
            '403 Forbidden Error',
            'The action you have requested is not allowed. Your session token may have expired; reload the page and try again.',
            'error_general',
            403
        );
    }
}

function integration_send_email($recipient, $subject, $body) {
    $GLOBALS['integration_last_smtp_error'] = null;
    $host = trim((string) config_item('mail_host'));
    $username = trim((string) config_item('mail_username'));
    $password = (string) config_item('mail_password');
    $from = trim((string) config_item('mail_from_address')) ?: $username;
    $from_name = trim((string) (config_item('mail_from_name') ?: 'EcoTrip'));
    $port = filter_var(config_item('mail_port') ?: 587, FILTER_VALIDATE_INT);
    $encryption = strtolower(trim((string) (config_item('mail_encryption') ?: 'tls')));

    if ($host === '' || $username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)
        || !$port || $port < 1 || $port > 65535 || !in_array($encryption, ['tls', 'ssl'], true)) {
        error_log('SMTP email is not configured or has invalid settings.');
        return false;
    }

    $message = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $message->isSMTP();
        $message->Host = $host;
        $message->SMTPAuth = true;
        $message->Username = $username;
        $message->Password = $password;
        $message->Port = $port;
        $message->Timeout = 15;
        $message->SMTPSecure = $encryption === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $message->CharSet = 'UTF-8';
        $message->setFrom($from, $from_name);
        $message->addAddress($recipient);
        $message->Subject = $subject;
        $message->Body = $body;
        $message->AltBody = trim(strip_tags($body));
        $message->isHTML(true);
        $message->send();
        return true;
    } catch (\PHPMailer\PHPMailer\Exception $exception) {
        $GLOBALS['integration_last_smtp_error'] = $exception->getMessage();
        error_log('SMTP email delivery failed: ' . $GLOBALS['integration_last_smtp_error']);
        return false;
    }
}

function integration_send_email_is_configured() {
    $host = trim((string) config_item('mail_host'));
    $username = trim((string) config_item('mail_username'));
    $password = (string) config_item('mail_password');
    $from = trim((string) config_item('mail_from_address')) ?: $username;
    $port = filter_var(config_item('mail_port') ?: 587, FILTER_VALIDATE_INT);
    $encryption = strtolower(trim((string) (config_item('mail_encryption') ?: 'tls')));
    return $host !== '' && $username !== '' && $password !== ''
        && filter_var($from, FILTER_VALIDATE_EMAIL)
        && $port && $port >= 1 && $port <= 65535
        && in_array($encryption, ['tls', 'ssl'], true);
}

function integration_google_configured() {
    return trim((string) config_item('google_client_id')) !== ''
        && trim((string) config_item('google_client_secret')) !== ''
        && trim((string) config_item('google_redirect_uri')) !== '';
}

function integration_queue_email($db, $event_key, $recipient, $subject, $body) {
    $existing = $db->table('email_notifications')
        ->where('event_key', $event_key)
        ->limit(1)
        ->get();
    if ($existing) return true;

    return $db->table('email_notifications')->insert([
        'event_key' => $event_key,
        'recipient' => $recipient,
        'subject' => $subject,
        'body' => $body,
        'status' => 'pending',
        'next_attempt_at' => date('Y-m-d H:i:s'),
    ]);
}

function integration_queue_trip_email($db, $event_key, array $trip, $subject, $message) {
    if (empty($trip['email']) || !filter_var($trip['email'], FILTER_VALIDATE_EMAIL)) return false;
    $trip_id = (int) ($trip['id'] ?? $trip['trip_id'] ?? 0);
    if ($trip_id < 1) return false;

    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $items = $db->table('itinerary_items')
        ->select('day_number, item_type, description, start_time, est_cost, est_co2_kg')
        ->where('trip_id', $trip_id)
        ->order_by('day_number')
        ->order_by('start_time')
        ->get_all();
    $itinerary = '<p>No itinerary items have been added yet.</p>';
    if ($items) {
        $itinerary = '';
        $current_day = null;
        foreach ($items as $item) {
            $day_number = (int) $item['day_number'];
            if ($current_day !== $day_number) {
                if ($current_day !== null) $itinerary .= '</ul>';
                $itinerary .= '<h4>Day ' . $day_number . '</h4><ul>';
                $current_day = $day_number;
            }
            $details = [];
            if (!empty($item['start_time'])) $details[] = $escape(substr((string) $item['start_time'], 0, 5));
            $details[] = '<strong>' . $escape($item['description'] ?: ucfirst((string) $item['item_type'])) . '</strong>';
            $details[] = $escape(ucfirst((string) $item['item_type']));
            if ((float) $item['est_cost'] > 0) $details[] = 'PHP ' . number_format((float) $item['est_cost'], 2);
            if ((float) $item['est_co2_kg'] > 0) $details[] = number_format((float) $item['est_co2_kg'], 1) . ' kg CO₂';
            $itinerary .= '<li>' . implode(' · ', $details) . '</li>';
        }
        if ($current_day !== null) $itinerary .= '</ul>';
    }
    $body = '<p>Hello ' . $escape($trip['traveler_name'] ?? 'traveler') . ',</p>'
        . '<p>' . $escape($message) . '</p>'
        . '<h2>' . $escape($trip['title']) . '</h2>'
        . '<p>' . $escape($trip['destination']) . '<br>'
        . $escape($trip['start_date']) . ' to ' . $escape($trip['end_date']) . '</p>'
        . '<h3>Your itinerary</h3>' . $itinerary
        . '<p>Have a thoughtful journey,<br>EcoTrip</p>';

    return integration_queue_email($db, $event_key, $trip['email'], $subject, $body);
}

function integration_queue_booking_status_email($db, $booking_id, $status) {
    if (!in_array($status, ['pending', 'paid', 'failed'], true)) return false;
    $booking = $db->table('bookings b')
        ->select('b.id, b.amount, b.payment_status, t.id AS trip_id, t.title, t.destination, t.start_date, t.end_date, u.name AS traveler_name, u.email')
        ->join('trips t', 't.id = b.trip_id')
        ->join('users u', 'u.id = t.user_id')
        ->where('b.id', (int) $booking_id)
        ->limit(1)
        ->get();
    if (!$booking || !filter_var($booking['email'], FILTER_VALIDATE_EMAIL)) return false;

    $messages = [
        'pending' => ['EcoTrip booking payment pending', 'Your booking was created and its payment is waiting for confirmation.'],
        'paid' => ['EcoTrip booking payment confirmed', 'Your payment has been confirmed. Thank you for your booking.'],
        'failed' => ['EcoTrip booking payment update', 'Your booking payment could not be completed. Please review your booking and try again if needed.'],
    ];
    $escape = static function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $body = '<p>Hello ' . $escape($booking['traveler_name']) . ',</p>'
        . '<p>' . $escape($messages[$status][1]) . '</p>'
        . '<p><strong>Trip:</strong> ' . $escape($booking['title']) . ' — ' . $escape($booking['destination']) . '<br>'
        . '<strong>Dates:</strong> ' . $escape($booking['start_date']) . ' to ' . $escape($booking['end_date']) . '<br>'
        . '<strong>Booking:</strong> #' . (int) $booking['id'] . '<br>'
        . '<strong>Amount:</strong> PHP ' . number_format((float) $booking['amount'], 2) . '<br>'
        . '<strong>Payment status:</strong> ' . $escape(ucfirst($status)) . '</p>'
        . '<p><a href="' . $escape(rtrim((string) (config_item('frontend_url') ?: 'http://localhost:5173'), '/') . '/bookings') . '">View your bookings</a></p>'
        . '<p>Have a thoughtful journey,<br>EcoTrip</p>';

    return integration_queue_email(
        $db,
        'booking:' . (int) $booking['id'] . ':' . $status,
        $booking['email'],
        $messages[$status][0],
        $body
    );
}

function integration_paymongo_secret_ready() {
    $secret = trim((string) config_item('paymongo_secret_key'));
    $production = strtolower(trim((string) (config_item('environment') ?: 'development'))) === 'production';
    return $production ? str_starts_with($secret, 'sk_live_') : str_starts_with($secret, 'sk_test_');
}

function integration_paymongo_link_paid($reference, $amount) {
    $secret = trim((string) config_item('paymongo_secret_key'));
    if (!integration_paymongo_secret_ready() || !str_starts_with((string) $reference, 'link_')) return false;

    $response = integration_http_request(
        'https://api.paymongo.com/v1/links/' . rawurlencode($reference),
        'GET',
        ['Authorization: Basic ' . base64_encode($secret . ':')]
    );
    $result = json_decode($response['body'], true);
    $attributes = $result['data']['attributes'] ?? [];
    $provider_amount = (int) ($attributes['amount'] ?? -1);
    $booking_amount = (int) round((float) $amount * 100);
    if ($response['status'] !== 200 || ($attributes['status'] ?? '') !== 'paid'
        || $provider_amount !== $booking_amount) {
        if ($response['status'] !== 200) {
            error_log('PayMongo payment verification returned HTTP ' . ($response['status'] ?: 502) . '.');
        }
        return false;
    }
    return true;
}

function integration_paymongo_checkout_paid($reference, $amount) {
    $secret = trim((string) config_item('paymongo_secret_key'));
    if (!integration_paymongo_secret_ready() || !str_starts_with((string) $reference, 'cs_')) return false;
    $response = integration_http_request(
        'https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($reference),
        'GET',
        ['Authorization: Basic ' . base64_encode($secret . ':')]
    );
    if ($response['status'] !== 200) return false;
    $result = json_decode($response['body'], true);
    $attributes = $result['data']['attributes'] ?? [];
    $payments = $attributes['payments'] ?? [];
    $expected = (int) round((float) $amount * 100);
    foreach ($payments as $payment) {
        $payment_attributes = $payment['attributes'] ?? $payment;
        if (($payment_attributes['status'] ?? '') === 'paid'
            && (int) ($payment_attributes['amount'] ?? -1) === $expected) return true;
    }

    $response = integration_http_request(
        'https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($reference) . '/payments',
        'GET',
        ['Authorization: Basic ' . base64_encode($secret . ':')]
    );
    if ($response['status'] !== 200) return false;
    $payments = json_decode($response['body'], true)['data'] ?? [];
    foreach ($payments as $payment) {
        $payment_attributes = $payment['attributes'] ?? $payment;
        if (($payment_attributes['status'] ?? '') === 'paid'
            && (int) ($payment_attributes['amount'] ?? -1) === $expected) return true;
    }
    return false;
}

function integration_json_response($status, array $payload) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function integration_client_ip() {
    return substr(trim((string) (lava_instance()->request->server('REMOTE_ADDR') ?? 'unknown')), 0, 45);
}

function integration_rate_limit_check($db, $scope, $identity, $limit, $window_seconds) {
    $key = hash('sha256', $scope . '\n' . $identity);
    try {
        $row = $db->table('rate_limits')->select('attempts, reset_at')->where('rate_key', $key)->limit(1)->get();
    } catch (Throwable $e) {
        error_log('EcoTrip rate-limit table unavailable: ' . $e->getMessage());
        return ['allowed' => false, 'retry_after' => max(1, (int) $window_seconds), 'unavailable' => true];
    }
    $now = time();
    if (!$row || strtotime((string) $row['reset_at']) <= $now) return ['allowed' => true, 'retry_after' => 0];
    $retry_after = max(0, strtotime((string) $row['reset_at']) - $now);
    return ['allowed' => (int) $row['attempts'] < $limit, 'retry_after' => $retry_after];
}

function integration_rate_limit_hit($db, $scope, $identity, $window_seconds) {
    $key = hash('sha256', $scope . '\n' . $identity);
    try {
        $window_seconds = max(1, (int) $window_seconds);
        $db->raw(
            'INSERT INTO rate_limits (rate_key, attempts, reset_at, updated_at)
             VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
             ON DUPLICATE KEY UPDATE
               attempts = IF(reset_at <= NOW(), 1, attempts + 1),
               reset_at = IF(reset_at <= NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), reset_at),
               updated_at = NOW()',
            [$key, $window_seconds, $window_seconds]
        );
        return true;
    } catch (Throwable $e) {
        error_log('EcoTrip rate-limit update failed: ' . $e->getMessage());
        return false;
    }
}

function integration_rate_limit_reset($db, $scope, $identity) {
    $key = hash('sha256', $scope . '\n' . $identity);
    try {
        $db->table('rate_limits')->where('rate_key', $key)->delete();
    } catch (Throwable $e) {
        error_log('EcoTrip rate-limit table unavailable: ' . $e->getMessage());
    }
}

function integration_http_request($url, $method = 'GET', array $headers = [], $body = null, $timeout = 90) {
    $retry_after = '';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADERFUNCTION => static function ($handle, $header) use (&$retry_after) {
            if (stripos($header, 'Retry-After:') === 0) {
                $retry_after = trim(substr($header, strlen('Retry-After:')));
            }
            return strlen($header);
        },
    ]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $content_type = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $error = curl_error($curl);
    curl_close($curl);
    return [
        'status' => $status,
        'body' => is_string($response) ? $response : '',
        'content_type' => $content_type,
        'error' => $error,
        'retry_after' => $retry_after,
    ];
}

function integration_osm_cache_dir() {
    $directory = ROOT_DIR . 'runtime' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'osm';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        error_log('OpenStreetMap cache directory could not be created.');
        return null;
    }
    return $directory;
}

function integration_osm_cached_json($path, $max_age) {
    if (!is_file($path) || filemtime($path) < time() - $max_age) return null;
    $content = file_get_contents($path);
    if (!is_string($content)) return null;
    $value = json_decode($content, true);
    return is_array($value) ? $value : null;
}

function integration_osm_cache_json($path, array $value) {
    $content = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($content) || file_put_contents($path, $content, LOCK_EX) === false) {
        error_log('OpenStreetMap search cache could not be written.');
    }
}

function integration_osm_geocode($destination, $cache_dir) {
    $cache_path = $cache_dir . DIRECTORY_SEPARATOR . 'geocode-' . hash('sha256', mb_strtolower($destination)) . '.json';
    $cached = integration_osm_cached_json($cache_path, 30 * 86400);
    if ($cached !== null) return $cached;

    $lock_path = $cache_dir . DIRECTORY_SEPARATOR . 'nominatim-rate-limit.lock';
    $lock = fopen($lock_path, 'c');
    if ($lock === false) {
        error_log('OpenStreetMap geocoding rate-limit lock could not be opened.');
        return ['error' => 'Location search is temporarily unavailable. Please try again later.'];
    }
    if (!flock($lock, LOCK_EX)) {
        fclose($lock);
        error_log('OpenStreetMap geocoding rate-limit lock could not be acquired.');
        return ['error' => 'Location search is temporarily unavailable. Please try again later.'];
    }

    $cached = integration_osm_cached_json($cache_path, 30 * 86400);
    if ($cached !== null) {
        flock($lock, LOCK_UN);
        fclose($lock);
        return $cached;
    }

    $last_request_path = $cache_dir . DIRECTORY_SEPARATOR . 'nominatim-last-request';
    $last_request = is_file($last_request_path) ? (float) file_get_contents($last_request_path) : 0;
    $wait = 1 - (microtime(true) - $last_request);
    if ($wait > 0) usleep((int) ($wait * 1000000));

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $destination,
        'format' => 'jsonv2',
        'limit' => 1,
        'countrycodes' => 'ph',
    ]);
    $response = integration_http_request(
        $url,
        'GET',
        ['User-Agent: EcoTrip/1.0 (destination hotel search)'],
        null,
        8
    );
    file_put_contents($last_request_path, (string) microtime(true), LOCK_EX);
    flock($lock, LOCK_UN);
    fclose($lock);

    $results = json_decode($response['body'], true);
    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($results)) {
        error_log('OpenStreetMap destination geocoding failed with HTTP ' . ($response['status'] ?: 502) . '.');
        return ['error' => 'Location search is temporarily unavailable. Please try again later.'];
    }
    if (!$results || !is_array($results[0] ?? null)) return ['error' => 'We could not find that destination in the Philippines. Try a nearby city or a more specific location.'];

    $location = [
        'lat' => (float) ($results[0]['lat'] ?? 0),
        'lon' => (float) ($results[0]['lon'] ?? 0),
        'name' => (string) ($results[0]['display_name'] ?? $destination),
    ];
    if ($location['lat'] < -90 || $location['lat'] > 90 || $location['lon'] < -180 || $location['lon'] > 180) {
        return ['error' => 'We could not find that destination. Try a nearby city or a more specific location.'];
    }

    integration_osm_cache_json($cache_path, $location);
    return $location;
}

function integration_osm_photon_hotels(array $location, $destination) {
    $url = 'https://photon.komoot.io/api/?' . http_build_query([
        'q' => 'hotel',
        'lat' => $location['lat'],
        'lon' => $location['lon'],
        'limit' => 30,
        'lang' => 'en',
        'osm_tag' => 'tourism:hotel',
    ]);
    $response = integration_http_request(
        $url,
        'GET',
        ['User-Agent: EcoTrip/1.0 (destination hotel search)'],
        null,
        10
    );
    $body = json_decode($response['body'], true);
    if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($body['features'] ?? null)) {
        error_log(sprintf(
            'OpenStreetMap Photon hotel search failed with HTTP %d: %s',
            $response['status'] ?: 502,
            $response['error']
        ));
        return null;
    }

    $places = [];
    $seen = [];
    foreach ($body['features'] as $feature) {
        $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $geometry = is_array($feature['geometry']['coordinates'] ?? null) ? $feature['geometry']['coordinates'] : [];
        $name = trim((string) ($properties['name'] ?? ''));
        $longitude = (float) ($geometry[0] ?? 0);
        $latitude = (float) ($geometry[1] ?? 0);
        if ($name === '' || in_array(mb_strtolower($name), [
            'hotel', 'hostel', 'motel', 'guest house', 'guest_house',
            'guesthouse', 'accommodation', 'resort', 'inn', 'lodging',
        ], true) || $longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) continue;
        if (($properties['osm_key'] ?? '') !== 'tourism' || ($properties['osm_value'] ?? '') !== 'hotel') continue;

        $cache_key = (string) ($properties['osm_type'] ?? '') . ':' . (string) ($properties['osm_id'] ?? '');
        if ($cache_key !== ':' && isset($seen[$cache_key])) continue;
        if ($cache_key !== ':') $seen[$cache_key] = true;

        $address_parts = array_filter([
            $properties['street'] ?? '',
            $properties['city'] ?? $properties['town'] ?? $properties['village'] ?? '',
        ], static function ($part) {
            return is_string($part) && trim($part) !== '';
        });
        $places[] = [
            'name' => $name,
            'address' => $address_parts ? implode(', ', $address_parts) : $destination,
            'kind' => 'Hotel',
            'website' => integration_osm_website_url($properties['website'] ?? ''),
        ];
    }

    return $places;
}

function integration_osm_website_url($value) {
    $website = trim((string) $value);
    if ($website === '') return '';
    if (!preg_match('/^https?:\/\//i', $website)) $website = 'https://' . $website;
    if (!filter_var($website, FILTER_VALIDATE_URL)) return '';

    $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $website : '';
}

function integration_openstreetmap_hotels($destination) {
    $destination = trim((string) $destination);
    if ($destination === '' || mb_strlen($destination) > 150) {
        return ['places' => [], 'error' => 'Enter a destination of 150 characters or fewer to search nearby places to stay.'];
    }

    $cache_dir = integration_osm_cache_dir();
    if ($cache_dir === null) {
        return ['places' => [], 'error' => 'Location search is temporarily unavailable. Please try again later.'];
    }

    $location = integration_osm_geocode($destination, $cache_dir);
    if (!empty($location['error'])) return ['places' => [], 'error' => $location['error']];

    $primary_url = trim((string) (config_item('osm_overpass_url') ?: 'https://overpass-api.de/api/interpreter'));
    $fallback_urls = trim((string) config_item('osm_overpass_fallback_urls'));
    if ($fallback_urls === '') $fallback_urls = 'https://overpass.private.coffee/api/interpreter';
    $endpoints = array_values(array_unique(array_merge(
        [$primary_url],
        array_map('trim', explode(',', $fallback_urls))
    )));
    if (count($endpoints) > 3) {
        error_log('Configure no more than two OSM Overpass fallback endpoints.');
        return ['places' => [], 'error' => 'OpenStreetMap hotel search is incorrectly configured.'];
    }
    foreach ($endpoints as $endpoint) {
        $url_parts = parse_url($endpoint);
        if (!is_array($url_parts) || ($url_parts['scheme'] ?? '') !== 'https' || empty($url_parts['host'])) {
            error_log('OSM Overpass endpoints must be HTTPS URLs with a host.');
            return ['places' => [], 'error' => 'OpenStreetMap hotel search is incorrectly configured.'];
        }
    }

    $results_cache_path = $cache_dir . DIRECTORY_SEPARATOR . 'accommodations-' . hash('sha256', strtolower('v4|' . implode('|', $endpoints) . '|' . $destination)) . '.json';
    $cached_places = integration_osm_cached_json($results_cache_path, 15 * 60);
    if ($cached_places !== null) return $cached_places;

    $query = '[out:json][timeout:8];('
        . 'nwr(around:5000,' . $location['lat'] . ',' . $location['lon'] . ')["tourism"~"^(hotel|hostel|guest_house|motel|resort|apartment|chalet|inn|camp_site)$"];'
        . ');out center tags 40;';
    $body = null;
    foreach ($endpoints as $endpoint) {
        $response = integration_http_request(
            $endpoint,
            'POST',
            ['Content-Type: application/x-www-form-urlencoded', 'User-Agent: EcoTrip/1.0 (destination hotel search)'],
            http_build_query(['data' => $query]),
            10
        );
        $body = json_decode($response['body'], true);
        if ($response['status'] >= 200 && $response['status'] < 300 && is_array($body)) break;

        $host = parse_url($endpoint, PHP_URL_HOST) ?: 'unknown endpoint';
        error_log(sprintf(
            'OpenStreetMap nearby accommodation search failed at %s with HTTP %d: %s',
            $host,
            $response['status'] ?: 502,
            $response['error']
        ));
        if (in_array($response['status'], [406, 429], true)) break;
        if ($response['status'] !== 0 && !in_array($response['status'], [502, 503, 504], true)) break;
        $body = null;
    }
    $places = [];
    $photon_available = false;
    if (is_array($body)) {
        foreach (is_array($body['elements'] ?? null) ? $body['elements'] : [] as $element) {
            $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
            $name = trim((string) ($tags['name:en'] ?? ''));
            if ($name === '') $name = trim((string) ($tags['name'] ?? ''));
            if ($name === '' || in_array(mb_strtolower($name), [
                'hotel', 'hostel', 'motel', 'guest house', 'guest_house',
                'guesthouse', 'accommodation', 'resort', 'inn', 'lodging',
            ], true)) continue;

            $address_parts = array_filter([
                $tags['addr:street'] ?? '',
                $tags['addr:city'] ?? $tags['addr:suburb'] ?? $tags['addr:town'] ?? $tags['addr:village'] ?? '',
            ], static function ($part) {
                return is_string($part) && trim($part) !== '';
            });
            $address = $address_parts ? implode(', ', $address_parts) : $destination;
            $kind = trim((string) ($tags['tourism'] ?? 'accommodation'));
            $places[] = [
                'name' => $name,
                'address' => $address,
                'kind' => ucwords(str_replace('_', ' ', $kind)),
                'website' => integration_osm_website_url($tags['contact:website'] ?? $tags['website'] ?? $tags['url'] ?? ''),
            ];
        }
    }

    if (!$places) {
        $photon_places = integration_osm_photon_hotels($location, $destination);
        if ($photon_places !== null) {
            $places = $photon_places;
            $photon_available = true;
        }
    }
    if (!$places && !is_array($body) && !$photon_available) {
        return ['places' => [], 'error' => 'Nearby accommodation search is temporarily unavailable. Please try again shortly.'];
    }

    $result = ['places' => $places, 'error' => '', 'location' => $location['name']];
    integration_osm_cache_json($results_cache_path, $result);
    return $result;
}

function integration_gemini_is_retryable(array $response) {
    return $response['error'] !== '' || in_array($response['status'], [408, 425, 429, 500, 502, 503, 504], true);
}

function integration_gemini_retry_delay($retry_after) {
    if ($retry_after !== '') {
        if (is_numeric($retry_after)) {
            $delay = (float) $retry_after;
        } else {
            $retry_at = strtotime($retry_after);
            $delay = $retry_at === false ? null : (float) ($retry_at - time());
        }
        if ($delay === null || $delay > 8) return null;
        return max(0, $delay);
    }
    return 1;
}

function integration_gemini_interact($input, $system_instruction, array $generation_config = [], array $response_format = []) {
    $api_key = trim((string) config_item('gemini_api_key'));
    if ($api_key === '') return ['status' => 503, 'text' => '', 'error' => 'Gemini is not configured.', 'temporary' => false];

    $model = trim((string) (config_item('gemini_model') ?: 'gemini-3.5-flash-lite'));
    $fallback_models = trim((string) config_item('gemini_fallback_models'));
    if ($fallback_models === '') $fallback_models = 'gemini-3.8-flash';
    $models = array_values(array_unique(array_merge([$model], array_map('trim', explode(',', $fallback_models)))));
    if (count($models) > 3) {
        return ['status' => 500, 'text' => '', 'error' => 'Configure no more than two Gemini fallback models.', 'temporary' => false];
    }
    foreach ($models as $candidate) {
        if ($candidate === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $candidate)) {
            return ['status' => 500, 'text' => '', 'error' => 'Invalid Gemini model configuration.', 'temporary' => false];
        }
    }

    $payload = [
        'input' => (string) $input,
        'system_instruction' => (string) $system_instruction,
        'store' => false,
        'generation_config' => $generation_config,
    ];
    if ($response_format) $payload['response_format'] = $response_format;
    $result = ['status' => 502, 'text' => '', 'error' => 'Gemini did not return a response.', 'model' => null, 'temporary' => false];
    foreach ($models as $index => $candidate) {
        $payload['model'] = $candidate;
        $attempts = $index === 0 ? 2 : 1;
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $response = integration_http_request(
                'https://generativelanguage.googleapis.com/v1beta/interactions',
                'POST',
                ['x-goog-api-key: ' . $api_key, 'Content-Type: application/json'],
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                60
            );
            $body = json_decode($response['body'], true);
            $text = '';
            foreach (is_array($body['steps'] ?? null) ? $body['steps'] : [] as $step) {
                if (($step['type'] ?? '') !== 'model_output') continue;
                foreach (is_array($step['content'] ?? null) ? $step['content'] : [] as $part) {
                    if (($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) $text .= $part['text'];
                }
            }
            $status = $response['status'] ?: 502;
            if ($status === 200 && ($body['status'] ?? '') !== 'completed') $status = 502;
            $error = $response['error'];
            if ($error === '' && is_array($body)) {
                $provider_error = is_array($body['error'] ?? null) ? $body['error'] : [];
                $error = (string) ($provider_error['message'] ?? $provider_error['status'] ?? $body['status'] ?? '');
            }
            if ($error === '' && $status >= 400) {
                $error = 'Provider returned HTTP ' . $status;
                if ($response['content_type'] !== '') $error .= ' (' . $response['content_type'] . ')';
                if (!is_array($body) && trim($response['body']) !== '') {
                    $error .= ': ' . substr(trim(strip_tags($response['body'])), 0, 300);
                }
            }
            $error = str_replace($api_key, '[redacted]', $error);
            $result = [
                'status' => $status,
                'text' => $text,
                'error' => $error,
                'model' => $candidate,
                'temporary' => integration_gemini_is_retryable(array_merge($response, ['status' => $status]))
                    || ($status >= 200 && $status < 300 && trim($text) === ''),
            ];
            if ($status >= 200 && $status < 300 && trim($text) !== '') return $result;
            if ($status === 404 && isset($models[$index + 1])) break;
            if (!$result['temporary']) return $result;
            if ($attempt + 1 < $attempts) {
                $delay = integration_gemini_retry_delay($response['retry_after']);
                if ($delay === null) break;
                usleep((int) ($delay * 1000000));
            }
        }
    }
    return $result;
}