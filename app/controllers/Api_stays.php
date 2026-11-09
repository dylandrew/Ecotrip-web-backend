<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_stays extends Api_v1 {
    public function stays_index() {
        $location = trim((string) $this->request->get('destination', ''));
        if (mb_strlen($location) > 150) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Destination is too long.');
        }
        if ($location !== '') {
            $this->limit_stay_search();
        }

        $type = (string) $this->request->get('type', 'all');
        if (!in_array($type, ['all', 'green', 'standard'], true)) {
            $type = 'all';
        }

        $nearby = $location !== ''
            ? integration_openstreetmap_hotels($location)
            : ['places' => [], 'error' => ''];

        api_json_response(200, [
            'stays' => $this->Stay_model->all_stays($location, $type),
            'nearby_hotels' => $nearby['places'],
            'hotel_search_error' => $nearby['error'],
        ]);
    }

    public function stays_show($id) {
        $stay = $this->Stay_model->find_stay((int) $id);
        if (!$stay) {
            api_json_response(404, null, 'NOT_FOUND', 'Stay not found.');
        }

        api_json_response(200, ['stay' => $stay]);
    }

    private function limit_stay_search() {
        $this->consume_rate_limit(
            'stay-search',
            'ip:' . integration_client_ip(),
            30,
            3600,
            'Too many stay destination searches. Try again later.'
        );
    }

    public function stays_nearby() {
        $location = trim((string) $this->request->get('destination', ''));
        if ($location === '' || mb_strlen($location) > 150) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Enter a destination.');
        }

        $this->limit_stay_search();
        $result = integration_openstreetmap_hotels($location);
        api_json_response(200, [
            'places' => $result['places'],
            'error' => $result['error'],
        ]);
    }

    public function stays_checkout($id = 0) {
        $userId = $this->uid();
        $body = $this->body();
        $tripId = (int) ($body['trip_id'] ?? 0);
        $trip = $this->Trip_model->find_for_user($tripId, $userId);
        if (!$trip) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }

        $nearbyDeposit = (int) $id > 0
            ? 0.0
            : $this->configured_nearby_deposit_amount();
        if ((int) $id <= 0 && $nearbyDeposit <= 0) {
            api_json_response(
                503,
                null,
                'PAYMONGO_DEPOSIT_NOT_CONFIGURED',
                'Direct checkout for mapped hotels requires a positive PayMongo deposit amount.'
            );
        }
        if (!integration_paymongo_secret_ready()) {
            api_json_response(
                503,
                null,
                'PAYMONGO_NOT_CONFIGURED',
                'PayMongo is not configured for this environment.'
            );
        }

        if ((int) $id > 0) {
            [$bookingId, $amount, $description] = $this->create_catalog_stay_booking(
                (int) $id,
                $trip,
                $tripId
            );
        } else {
            [$bookingId, $amount, $description] = $this->create_nearby_stay_booking(
                $body,
                $tripId,
                $nearbyDeposit
            );
            if ($amount === 0.0) {
                api_json_response(201, [
                    'booking_id' => $bookingId,
                    'payment_required' => false,
                    'payment_status' => 'reservation',
                ]);
            }
        }

        $checkoutUrl = $this->create_checkout($bookingId, $amount, $description);
        api_json_response(200, [
            'booking_id' => $bookingId,
            'amount' => $amount,
            'checkout_url' => $checkoutUrl,
        ]);
    }

    private function create_catalog_stay_booking($stayId, array $trip, $tripId) {
        $stay = $this->Stay_model->find_stay($stayId);
        if (!$stay) {
            api_json_response(404, null, 'NOT_FOUND', 'Stay not found.');
        }

        $start = new DateTime($trip['start_date']);
        $end = new DateTime($trip['end_date']);
        $amount = round((float) $stay['price_per_night'] * $start->diff($end)->days, 2);
        if ($amount <= 0 || $amount > 1000000) {
            api_json_response(
                422,
                null,
                'INVALID_STAY_RATE',
                'This stay does not have a valid server-side rate.'
            );
        }

        $bookingId = $this->Booking_model->create_stay_booking($tripId, $stayId, $amount);
        return [$bookingId, $amount, $stay['name']];
    }

    private function configured_nearby_deposit_amount() {
        $configuredRate = trim((string) config_item('paymongo_nearby_deposit_amount'));
        if ($configuredRate === '') {
            return 0.0;
        }
        $amount = filter_var($configuredRate, FILTER_VALIDATE_FLOAT);
        if ($amount === false || $amount < 1 || $amount > 1000000) {
            api_json_response(
                503,
                null,
                'INVALID_STAY_RATE',
                'The configured nearby stay deposit rate is invalid.'
            );
        }
        return round((float) $amount, 2);
    }

    private function create_nearby_stay_booking(array $body, $tripId, $amount) {
        $name = mb_substr(trim((string) ($body['reservation_name'] ?? '')), 0, 150);
        $address = mb_substr(trim((string) ($body['reservation_address'] ?? '')), 0, 150);
        if ($name === '' || $address === '') {
            api_json_response(
                422,
                null,
                'VALIDATION_ERROR',
                'Reservation name and address are required.'
            );
        }

        $bookingId = $this->Booking_model->create_nearby_booking(
            $tripId,
            $name . ' — ' . $address,
            $amount
        );
        $description = 'Hotel reservation deposit — ' . $name;

        return [$bookingId, $amount, $description];
    }

    private function create_checkout($bookingId, $amount, $description) {
        $secret = trim((string) config_item('paymongo_secret_key'));
        $frontendUrl = rtrim((string) (config_item('frontend_url') ?: 'http://localhost:5173'), ' /');
        $payload = [
            'data' => [
                'attributes' => [
                    'line_items' => [[
                        'name' => mb_substr($description, 0, 120),
                        'amount' => (int) round($amount * 100),
                        'currency' => 'PHP',
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => ['gcash', 'paymaya', 'card', 'qrph'],
                    'success_url' => $frontendUrl . '/payment/success?booking_id=' . (int) $bookingId,
                    'cancel_url' => $frontendUrl . '/payment/cancelled?booking_id=' . (int) $bookingId,
                    'reference_number' => 'ECO-' . $bookingId . '-' . bin2hex(random_bytes(4)),
                    'description' => $description,
                ],
            ],
        ];

        $response = integration_http_request(
            'https://api.paymongo.com/v1/checkout_sessions',
            'POST',
            [
                'Authorization: Basic ' . base64_encode($secret . ':'),
                'Content-Type: application/json',
            ],
            json_encode($payload)
        );
        $result = json_decode($response['body'], true);
        $checkoutUrl = $result['data']['attributes']['checkout_url'] ?? '';
        $reference = $result['data']['id'] ?? '';

        if (
            $response['status'] < 200
            || $response['status'] >= 300
            || !is_string($checkoutUrl)
            || !str_starts_with($checkoutUrl, 'https://')
            || !is_string($reference)
            || $reference === ''
        ) {
            $this->Booking_model->set_status_by_id($bookingId, 'failed');
            api_json_response(502, null, 'PAYMONGO_ERROR', 'PayMongo could not create checkout.');
        }

        $this->Booking_model->set_payment_reference($bookingId, $reference);
        return $checkoutUrl;
    }
}
