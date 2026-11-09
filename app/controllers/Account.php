<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Account extends Controller {
	public function __construct() {
		parent::__construct();
		$this->call->model('User_model');
		$this->call->model('Password_reset_model');
	}

	private function go($path) { redirect($path); exit; }

	public function password() {
		auth_require_login();
		$user = auth_current_user();
		$record = $this->User_model->find_by_id($user['id']);
		$this->call->view('pages/account_password', [
			'title' => 'Change Password',
			'user' => $user,
			'phone' => $record['phone'] ?? '',
			'address' => $record['address'] ?? '',
			'has_password' => $record && !empty($record['password_hash']),
			'google_connected' => $record && !empty($record['google_sub']),
			'google_sign_in_enabled' => integration_google_configured(),
			'flash' => $this->session->flashdata('success'),
			'flash_error' => $this->session->flashdata('error'),
		]);
	}

	public function address_update() {
		auth_require_login();
		integration_require_csrf();
		$user = auth_current_user();
		$address = mb_substr(trim(strip_tags((string) ($_POST['address'] ?? ''))), 0, 255);
		if ($address === '') {
			$this->session->set_flashdata('error', 'Enter a complete home address.');
			$this->go('account/password');
		}
		$this->User_model->update_address((int) $user['id'], $address);
		$this->session->set_flashdata('success', 'Home address updated successfully.');
		$this->go('account/password');
	}

	public function password_update() {
		auth_require_login();
		integration_require_csrf();
		$user = auth_current_user();
		$record = $this->User_model->find_by_id($user['id']);
		$current = (string) ($_POST['current_password'] ?? '');
		$new = (string) ($_POST['new_password'] ?? '');
		$confirm = (string) ($_POST['confirm_password'] ?? '');
		if (!$record || (!empty($record['password_hash']) && !password_verify($current, $record['password_hash']))) {
			$this->session->set_flashdata('error', 'Current password is incorrect.');
			$this->go('account/password');
		}
		if (strlen($new) < 12 || strlen($new) > 200 || $new !== $confirm || password_verify($new, $record['password_hash'])) {
			$this->session->set_flashdata('error', 'Use a new password of at least 12 characters and enter it the same way twice.');
			$this->go('account/password');
		}
		$this->User_model->update_password($user['id'], password_hash($new, PASSWORD_DEFAULT));
		$this->Password_reset_model->delete_for_email($user['email']);
		$this->session->sess_regenerate(true);
		$this->session->set_flashdata('success', 'Password changed successfully.');
		$this->go('account/password');
	}

	public function export_data() {
		auth_require_login();
		$user = auth_current_user();
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="ecotrip-account-' . (int) $user['id'] . '.json"');
		echo json_encode($this->User_model->export_data((int) $user['id']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	public function delete() {
		auth_require_login();
		integration_require_csrf();
		$user = auth_current_user();
		$record = $this->User_model->find_by_id((int) $user['id']);
		$password = (string) ($_POST['current_password'] ?? '');
		if (!$record || (!empty($record['password_hash']) && !password_verify($password, $record['password_hash']))) {
			$this->session->set_flashdata('error', 'Enter your current password to delete your account.');
			$this->go('account/password');
		}
		if (($record['role'] ?? '') === 'admin' && $this->User_model->count_admins() <= 1) {
			$this->session->set_flashdata('error', 'The last administrator account cannot be deleted.');
			$this->go('account/password');
		}
		$this->User_model->delete_user((int) $user['id']);
		$this->session->sess_destroy();
		redirect('/');
		exit;
	}
}