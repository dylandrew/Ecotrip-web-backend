<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Stays extends Controller {
    public function __construct() {
        parent::__construct();
        $this->call->model('Stay_model');
        $this->call->model('Trip_model');
        $this->call->model('Booking_model');
    }

    private function go($path) { redirect($path); exit; }
    private function paymongo_test_ready() {
        return integration_paymongo_secret_ready();
    }

    private function nearby_deposit_amount() {
        $configured = trim((string) config_item('paymongo_nearby_deposit_amount'));
        if ($configured === '') return 0.0;
        $amount = filter_var($configured, FILTER_VALIDATE_FLOAT);
        return $amount !== false && $amount >= 1 && $amount <= 1000000 ? round($amount, 2) : 0.0;
    }

    private function create_paymongo_checkout($booking_id, $amount, $description, $remarks) {
        $secret = trim((string) config_item('paymongo_secret_key'));
        $frontend_url = rtrim(trim((string) (config_item('frontend_url') ?: 'http://localhost:5173')), '/');
        $reference_number = 'ECO-STAY-' . $booking_id . '-' . bin2hex(random_bytes(4));
        $payload = ['data' => ['attributes' => [
            'line_items' => [[
                'name' => mb_substr($description, 0, 120),
                'amount' => (int) round($amount * 100),
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            'payment_method_types' => ['gcash', 'paymaya', 'card', 'qrph'],
            'success_url' => $frontend_url . '/payment/success?booking_id=' . (int) $booking_id,
            'cancel_url' => $frontend_url . '/payment/cancelled?booking_id=' . (int) $booking_id,
            'reference_number' => $reference_number,
            'description' => $description,
            'remarks' => $remarks,
        ]]];
        $response = integration_http_request('https://api.paymongo.com/v1/checkout_sessions', 'POST', [
            'Authorization: Basic ' . base64_encode($secret . ':'),
            'Content-Type: application/json',
        ], json_encode($payload));
        $result = json_decode($response['body'], true);
        $attributes = $result['data']['attributes'] ?? [];
        $checkout_url = $attributes['checkout_url'] ?? '';
        $reference = $result['data']['id'] ?? '';
        if ($response['status'] < 200 || $response['status'] >= 300
            || !$reference || !is_string($checkout_url) || !str_starts_with($checkout_url, 'https://')) {
            error_log(sprintf(
                'PayMongo checkout creation failed with HTTP %d: %s',
                $response['status'] ?: 502,
                $response['error']
            ));
            return null;
        }
        $this->Booking_model->set_payment_reference($booking_id, $reference);
        return $checkout_url;
    }

    public function index() {
        $location = trim((string) ($_GET['destination'] ?? ''));
        if (mb_strlen($location) > 150) $location = mb_substr($location, 0, 150);
        if ($location !== '') {
            $rate_key = 'user:' . auth_user_id() . ':ip:' . integration_client_ip();
            $rate = integration_rate_limit_check($this->db, 'stay-search', $rate_key, 30, 3600);
            if (!empty($rate['unavailable'])) {
                $this->session->set_flashdata('error', 'Stay search is temporarily unavailable. Try again shortly.');
                $this->go('stays');
            }
            if (!$rate['allowed']) {
                $this->session->set_flashdata('error', 'Too many stay searches. Try again in ' . ceil($rate['retry_after'] / 60) . ' minutes.');
                $this->go('stays');
            }
            if (!integration_rate_limit_hit($this->db, 'stay-search', $rate_key, 3600)) {
                $this->session->set_flashdata('error', 'Stay search is temporarily unavailable. Try again shortly.');
                $this->go('stays');
            }
        }
        $type = (string) ($_GET['type'] ?? 'all');
        if (!in_array($type, ['all', 'green', 'standard'], true)) $type = 'all';
        $hotel_search = $location !== ''
            ? integration_openstreetmap_hotels($location)
            : ['places' => [], 'error' => ''];
        $current_user_id = auth_user_id();
        $nearby_trips = $current_user_id > 0
            ? array_values(array_filter($this->Trip_model->all_for_user($current_user_id), function ($trip) {
                return $trip['end_date'] >= date('Y-m-d') && $trip['end_date'] > $trip['start_date'];
            }))
            : [];
        $this->call->view('stays/index', [
            'title' => 'Places to Stay',
            'stays' => $this->Stay_model->all_stays($location, $type),
            'destination' => $location,
            'stay_type' => $type,
            'nearby_hotels' => $hotel_search['places'],
            'hotel_search_error' => $hotel_search['error'],
            'nearby_trips' => $nearby_trips,
            'nearby_deposit_amount' => $this->nearby_deposit_amount(),
            'paymongo_test_ready' => $this->paymongo_test_ready(),
            'flash_error' => $this->session->flashdata('error'),
        ]);
    }

    public function show($id) {
        auth_require_login();
        $stay = $this->Stay_model->find_stay((int) $id);
        if (!$stay) { http_response_code(404); $this->call->view('pages/not_found', ['title' => 'Not found']); return; }
        $trips = array_values(array_filter($this->Trip_model->all_for_user(auth_user_id()), function ($trip) {
            return $trip['end_date'] >= date('Y-m-d');
        }));
        $this->call->view('stays/show', [
            'title' => $stay['name'],
            'stay' => $stay,
            'trips' => $trips,
            'paymongo_ready' => $this->paymongo_test_ready(),
            'flash' => $this->session->flashdata('success'),
            'flash_error' => $this->session->flashdata('error'),
        ]);
    }

    public function checkout($id) {
        auth_require_login();
        integration_require_csrf();
        $stay = $this->Stay_model->find_stay((int) $id);
        $trip = $this->Trip_model->find_for_user((int) ($_POST['trip_id'] ?? 0), auth_user_id());
        if (!$stay || !$trip || $trip['end_date'] < date('Y-m-d') || $trip['end_date'] <= $trip['start_date']) {
            $this->session->set_flashdata('error', 'Choose a stay and a future trip with at least one overnight.');
            $this->go('stays/' . (int) $id);
        }
        if (!$this->paymongo_test_ready()) {
            $production = strtolower(trim((string) (config_item('environment') ?: 'development'))) === 'production';
            $this->session->set_flashdata('error', $production ? 'Production checkout requires a PAYMONGO_SECRET_KEY that starts with sk_live_.' : 'PayMongo test checkout requires a PAYMONGO_SECRET_KEY that starts with sk_test_.');
            $this->go('stays/' . (int) $id);
        }

        $start = new DateTime($trip['start_date']);
        $end = new DateTime($trip['end_date']);
        $nights = (int) $start->diff($end)->days;
        $amount = round((float) $stay['price_per_night'] * $nights, 2);
        if ($amount <= 0 || $amount > 1000000) {
            $this->session->set_flashdata('error', 'The stay total is outside PayMongo\'s supported range.');
            $this->go('stays/' . (int) $id);
        }

        $booking_id = $this->Booking_model->create_stay_booking($trip['id'], $stay['id'], $amount);
        $checkout_url = $this->create_paymongo_checkout(
            $booking_id,
            $amount,
            $stay['name'] . ' · ' . $nights . ' nights for ' . $trip['title'],
            'EcoTrip test-mode stay booking ' . $booking_id
        );
        if ($checkout_url === null) {
            $failed_reference = 'failed-' . $booking_id;
            $this->Booking_model->set_payment_reference($booking_id, $failed_reference);
            $this->Booking_model->set_status_by_reference($failed_reference, 'failed');
            $this->session->set_flashdata('error', 'PayMongo could not create checkout. Verify the configured key and PayMongo account, then try again.');
            $this->go('stays/' . (int) $id);
        }
        header('Location: ' . $checkout_url, true, 303);
        exit;
    }

    public function nearby_checkout() {
        auth_require_login();
        integration_require_csrf();
        $destination = trim((string) ($_POST['destination'] ?? ''));
        $return_to_search = 'stays?' . http_build_query(['destination' => $destination]);

        $name = mb_substr(trim((string) ($_POST['reservation_name'] ?? '')), 0, 150);
        $address = mb_substr(trim((string) ($_POST['reservation_address'] ?? '')), 0, 150);
        $trip = $this->Trip_model->find_for_user((int) ($_POST['trip_id'] ?? 0), auth_user_id());
        if ($name === '' || $address === '' || $destination === '' || mb_strlen($destination) > 150
            || !$trip || $trip['end_date'] < date('Y-m-d') || $trip['end_date'] <= $trip['start_date']) {
            $this->session->set_flashdata('error', 'Choose a valid trip and a nearby stay before saving a reservation request.');
            $this->go($return_to_search);
        }

        $reservation_name = mb_substr($name . ' — ' . $address, 0, 150);
        $amount = $this->nearby_deposit_amount();
        if ($amount <= 0) {
            $this->Booking_model->create_nearby_booking($trip['id'], $reservation_name);
            $this->session->set_flashdata('success', 'Reservation request saved. No payment was collected; confirm availability and price with the stay.');
            $this->go($return_to_search);
        }
        if (!$this->paymongo_test_ready()) {
            $production = strtolower(trim((string) (config_item('environment') ?: 'development'))) === 'production';
            $this->session->set_flashdata('error', $production ? 'Production checkout requires a PAYMONGO_SECRET_KEY that starts with sk_live_.' : 'PayMongo checkout requires a PAYMONGO_SECRET_KEY that starts with sk_test_.');
            $this->go($return_to_search);
        }

        $booking_id = $this->Booking_model->create_nearby_booking($trip['id'], $reservation_name, $amount);
        $checkout_url = $this->create_paymongo_checkout(
            $booking_id,
            $amount,
            'EcoTrip hotel reservation deposit — ' . $name,
            'Nearby OpenStreetMap reservation for trip ' . $trip['id'] . '; availability must be confirmed directly with the hotel.'
        );
        if (!$checkout_url) {
            $this->Booking_model->set_status_by_id($booking_id, 'failed');
            $this->session->set_flashdata('error', 'PayMongo could not create checkout. Verify the configured key and try again.');
            $this->go($return_to_search);
        }
        header('Location: ' . $checkout_url, true, 303);
        exit;
    }
}
