<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Reminders extends Controller {
	public function __construct() {
		parent::__construct();
		auth_require_login();
		$this->call->model('Reminder_model');
	}

	private function uid() { return auth_user_id(); }
	private function go($path) { redirect($path); exit; }

	public function index() {
		$this->call->view('pages/reminders', [
			'title' => 'Trip Reminders',
			'trips' => $this->Reminder_model->trips_for_user($this->uid()),
			'reminders' => $this->Reminder_model->all_for_user($this->uid()),
			'flash' => $this->session->flashdata('success'),
			'flash_error' => $this->session->flashdata('error'),
		]);
	}

	public function store() {
		integration_require_csrf();
		$trip_id = (int) ($_POST['trip_id'] ?? 0);
		$message = trim(strip_tags((string) ($_POST['message'] ?? '')));
		$send_at = DateTime::createFromFormat('Y-m-d\\TH:i', (string) ($_POST['send_at'] ?? ''));
		if (!$this->Reminder_model->belongs_to_user($trip_id, $this->uid()) || !$send_at || $send_at <= new DateTime() || $message === '' || strlen($message) > 255) {
			$this->session->set_flashdata('error', 'Choose your trip, enter a future time, and keep the message under 255 characters.');
			$this->go('reminders');
		}
		$this->Reminder_model->create_reminder($trip_id, $message, $send_at->format('Y-m-d H:i:s'));
		$this->go('reminders');
	}
}
