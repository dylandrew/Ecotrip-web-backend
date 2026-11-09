<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Bookings extends Controller {
	public function __construct() {
		parent::__construct();
		$this->call->model('Booking_model');
		$this->call->model('Trip_model');
	}

	private function uid() { return auth_user_id(); }
	private function go($path) { redirect($path); exit; }

	private function verify_payment($booking) {
		if (!$booking || $booking['payment_status'] === 'paid') return $booking && $booking['payment_status'] === 'paid';
		$reference = (string) ($booking['paymongo_ref'] ?? '');
		$paid = str_starts_with($reference, 'cs_')
			? integration_paymongo_checkout_paid($reference, $booking['amount'])
			: integration_paymongo_link_paid($reference, $booking['amount']);
		if (!$paid) return false;

		$this->Booking_model->set_status_by_reference($reference, 'paid');
		return true;
	}

	public function index() {
		auth_require_login();
		if (($_GET['payment'] ?? '') === 'success') {
			$this->session->set_flashdata('success', 'Payment completed. PayMongo is confirming the transaction and updating your booking.');
		} elseif (($_GET['payment'] ?? '') === 'cancelled') {
			$this->session->set_flashdata('error', 'Payment was cancelled. No payment was completed.');
		}
		$bookings = $this->Booking_model->all_for_user($this->uid());
		foreach (array_slice($bookings, 0, 3) as $booking) {
			if ($booking['payment_status'] === 'pending') $this->verify_payment($booking);
		}
		$this->call->view('pages/bookings', [
			'title' => 'Bookings & Offsets',
			'trips' => $this->Trip_model->all_for_user($this->uid()),
			'bookings' => $this->Booking_model->all_for_user($this->uid()),
			'paymongo_ready' => trim((string) config_item('paymongo_secret_key')) !== '',
			'flash' => $this->session->flashdata('success'),
			'flash_error' => $this->session->flashdata('error'),
		]);
	}

	public function verify($id) {
		auth_require_login();
		integration_require_csrf();
		$booking = $this->Booking_model->find_for_user((int) $id, $this->uid());
		if (!$booking) {
			http_response_code(404);
			$this->call->view('pages/not_found', ['title' => 'Booking not found']);
			return;
		}
		if ($this->verify_payment($booking)) {
			$this->go('bookings/' . (int) $id . '/receipt');
		}
		$this->session->set_flashdata('error', 'PayMongo has not confirmed this payment yet. Wait a moment, then check again.');
		$this->go('bookings');
	}

	public function payment_success($id) {
		$frontend = rtrim((string) (config_item('frontend_url') ?: 'http://localhost:5173'), '/');
		header('Location: ' . $frontend . '/payment/success?booking_id=' . (int) $id, true, 302);
		exit;
	}

	public function payment_cancelled($id) {
		$frontend = rtrim((string) (config_item('frontend_url') ?: 'http://localhost:5173'), '/');
		header('Location: ' . $frontend . '/payment/cancelled?booking_id=' . (int) $id, true, 302);
		exit;
	}

	public function receipt($id) {
		auth_require_login();
		$booking = $this->Booking_model->find_for_user((int) $id, $this->uid());
		if (!$booking || $booking['payment_status'] !== 'paid') {
			$this->session->set_flashdata('error', 'A receipt is available after PayMongo confirms payment.');
			$this->go('bookings');
		}
		$this->call->view('pages/receipt', ['title' => 'Payment Receipt', 'booking' => $booking]);
	}

	public function checkout() {
		auth_require_login();
		integration_require_csrf();
		$trip_id = (int) ($_POST['trip_id'] ?? 0);
		$trip = $this->Trip_model->find_for_user($trip_id, $this->uid());
		$amount = filter_var($_POST['offset_amount'] ?? null, FILTER_VALIDATE_FLOAT);
		$secret = trim((string) config_item('paymongo_secret_key'));
		if (!$trip || $amount === false || $amount < 1 || $amount > 1000000) {
			$this->session->set_flashdata('error', 'Choose a trip and an offset amount between PHP 1 and PHP 1,000,000.');
			$this->go('bookings');
		}
		if (!integration_paymongo_secret_ready()) {
			$production = strtolower(trim((string) (config_item('environment') ?: 'development'))) === 'production';
			$this->session->set_flashdata('error', $production ? 'Production checkout requires a PAYMONGO_SECRET_KEY that starts with sk_live_.' : 'PayMongo test checkout requires a PAYMONGO_SECRET_KEY that starts with sk_test_.');
			$this->go('bookings');
		}

		$booking_id = $this->Booking_model->create_offset($trip_id, round($amount, 2));
		$payload = ['data' => ['attributes' => [
			'amount' => (int) round($amount * 100),
			'currency' => 'PHP',
			'description' => 'EcoTrip carbon offset for ' . $trip['title'],
			'reference_number' => 'ECO-' . $booking_id . '-' . bin2hex(random_bytes(4)),
			'remarks' => 'Carbon offset contribution for EcoTrip trip ' . $trip_id,
		]]];
		$response = integration_http_request('https://api.paymongo.com/v1/links', 'POST', [
			'Authorization: Basic ' . base64_encode($secret . ':'),
			'Content-Type: application/json',
		], json_encode($payload));
		$result = json_decode($response['body'], true);
		$attributes = $result['data']['attributes'] ?? [];
		$checkout_url = $attributes['checkout_url'] ?? '';
		$reference = $result['data']['id'] ?? '';
		if ($response['status'] < 200 || $response['status'] >= 300 || !$checkout_url || !$reference || !str_starts_with($checkout_url, 'https://')) {
			$this->Booking_model->set_payment_reference($booking_id, 'failed-' . $booking_id);
			$this->Booking_model->set_status_by_reference('failed-' . $booking_id, 'failed');
			$this->session->set_flashdata('error', 'PayMongo could not create checkout. Check the key and account configuration, then try again.');
			$this->go('bookings');
		}
		$this->Booking_model->set_payment_reference($booking_id, $reference);
		header('Location: ' . $checkout_url, true, 303);
		exit;
	}

	public function webhook() {
		$secret = trim((string) config_item('paymongo_webhook_secret'));
		$raw = file_get_contents('php://input');
		$signature = (string) ($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '');
		$parts = [];
		foreach (explode(',', $signature) as $part) {
			$pair = explode('=', trim($part), 2);
			if (count($pair) === 2) $parts[$pair[0]] = $pair[1];
		}
		$timestamp = $parts['t'] ?? '';
		$signature_key = (config_item('environment') ?: 'development') === 'production' ? 'li' : 'te';
		$received = $parts[$signature_key] ?? '';
		$expected = $secret !== '' ? hash_hmac('sha256', $timestamp . '.' . $raw, $secret) : '';
		if ($secret === '' || $timestamp === '' || $received === '' || !hash_equals($expected, $received)) {
			http_response_code(401);
			exit('Invalid webhook signature');
		}

		$event = json_decode($raw, true);
		if (!is_array($event)) {
			http_response_code(400);
			exit('Invalid webhook payload');
		}
		$type = $event['data']['attributes']['type'] ?? '';
		$reference = $event['data']['attributes']['data']['id'] ?? '';
		$event_id = $event['data']['id'] ?? '';
		if ($type === 'link.payment.paid' || $type === 'checkout_session.payment.paid') {
			if ($type === 'checkout_session.payment.paid' && is_array($event['data']['attributes']['data']['attributes'] ?? null)) {
				$reference = $event['data']['attributes']['data']['attributes']['checkout_session_id']
					?? $event['data']['attributes']['data']['id']
					?? $reference;
			}
			if (!is_string($reference) || $reference === '' || !is_string($event_id) || $event_id === '') {
				http_response_code(400);
				exit('Invalid payment event');
			}
			if ($this->Booking_model->webhook_event_exists($event_id)) {
				http_response_code(200);
				exit('ok');
			}
			$booking = $this->Booking_model->find_by_reference($reference);
			if (!$booking) {
				$this->Booking_model->record_webhook_event($event_id, $type);
				http_response_code(200);
				exit('ok');
			}
			$verified = str_starts_with($reference, 'cs_')
				? integration_paymongo_checkout_paid($reference, $booking['amount'])
				: integration_paymongo_link_paid($reference, $booking['amount']);
			if (!$verified) {
				http_response_code(503);
				exit('Payment verification is pending');
			}
			$this->Booking_model->set_status_by_reference($reference, 'paid');
			$this->Booking_model->record_webhook_event($event_id, $type);
		}
		http_response_code(200);
		exit('ok');
	}
}
