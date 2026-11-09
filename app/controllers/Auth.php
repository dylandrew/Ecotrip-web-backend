<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Auth extends Controller {
	public function __construct() {
		parent::__construct();
		$this->call->model('User_model');
	}

	private function go($path) { redirect($path); exit; }

	public function login() {
		if (auth_current_user()) {
			$return_to = trim((string) ($_SESSION['auth_return_to'] ?? ''));
			if ($return_to !== '') {
				unset($_SESSION['auth_return_to']);
				header('Location: ' . $return_to, true, 303);
				exit;
			}
			$this->go((auth_current_user()['role'] ?? '') === 'admin' ? 'admin' : 'dashboard');
		}
		$this->call->view('pages/login', [
			'title' => 'Log in',
			'flash' => $this->session->flashdata('success'),
			'flash_error' => $this->session->flashdata('error'),
			'google_sign_in_enabled' => integration_google_configured(),
		]);
	}

	public function login_submit() {
		integration_require_csrf();
		$email = strtolower(trim((string) ($_POST['email'] ?? '')));
		$password = (string) ($_POST['password'] ?? '');
		$rate_key = 'login:' . $email . ':' . integration_client_ip();
		$rate = integration_rate_limit_check($this->db, 'login-failures', $rate_key, 5, 900);
		if (!empty($rate['unavailable'])) {
			$this->session->set_flashdata('error', 'Sign-in is temporarily unavailable. Try again shortly.');
			$this->go('login');
		}
		if (!$rate['allowed']) {
			$this->session->set_flashdata('error', 'Too many failed login attempts. Try again in ' . ceil($rate['retry_after'] / 60) . ' minutes.');
			$this->go('login');
		}
		$user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->User_model->find_by_email($email) : null;
		if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
			if (!integration_rate_limit_hit($this->db, 'login-failures', $rate_key, 900)) {
				$this->session->set_flashdata('error', 'Sign-in is temporarily unavailable. Try again shortly.');
				$this->go('login');
			}
			$this->session->set_flashdata('error', 'Email or password is incorrect.');
			$this->go('login');
		}

		integration_rate_limit_reset($this->db, 'login-failures', $rate_key);
		$this->establish_session($user);
		$this->session->set_flashdata('success', 'Welcome back, ' . $user['name'] . '.');
		$return_to = trim((string) ($_SESSION['auth_return_to'] ?? ''));
		if ($return_to !== '') {
			unset($_SESSION['auth_return_to']);
			header('Location: ' . $return_to, true, 303);
			exit;
		}
		$this->go($user['role'] === 'admin' ? 'admin' : 'dashboard');
	}

	private function establish_session($user) {
		$this->session->sess_regenerate(true);
		$this->session->set_userdata('ecotrip_user', [
			'id' => (int) $user['id'],
			'name' => $user['name'],
			'email' => $user['email'],
			'role' => $user['role'],
		]);
	}

	public function register() {
		if (auth_current_user()) $this->go('dashboard');
		$this->call->view('pages/register', [
			'title' => 'Create account',
			'flash_error' => $this->session->flashdata('error'),
			'google_sign_in_enabled' => integration_google_configured(),
		]);
	}

	public function register_submit() {
		integration_require_csrf();
		$name = trim(strip_tags((string) ($_POST['name'] ?? '')));
		$email = strtolower(trim((string) ($_POST['email'] ?? '')));
		$password = (string) ($_POST['password'] ?? '');
		$address = mb_substr(trim(strip_tags((string) ($_POST['address'] ?? ''))), 0, 255);
		if ($name === '' || mb_strlen($name) > 100 || $address === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || strlen($password) < 12 || strlen($password) > 200) {
			$this->session->set_flashdata('error', 'Enter your name, home address, valid email, and a password with at least 12 characters.');
			$this->go('register');
		}
		if ($this->User_model->find_by_email($email)) {
			$this->session->set_flashdata('error', 'An account with that email already exists.');
			$this->go('register');
		}
		$this->User_model->create_user($name, $email, password_hash($password, PASSWORD_DEFAULT), '', 'traveler', $address);
		$this->session->set_flashdata('success', 'Account created. Sign in to start planning.');
		$this->go('login');
	}

	public function logout() {
		integration_require_csrf();
		$this->session->sess_destroy();
		redirect('/');
	}

	public function forgot_password() {
		$this->call->view('pages/forgot_password', [
			'title' => 'Forgot password',
			'flash' => $this->session->flashdata('success'),
			'flash_error' => $this->session->flashdata('error'),
		]);
	}

	public function forgot_password_submit() {
		integration_require_csrf();
		$email = strtolower(trim((string) ($_POST['email'] ?? '')));
		if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 150) {
			$user = $this->User_model->find_by_email($email);
			if ($user) {
				$this->call->model('Password_reset_model');
				$previous = $this->Password_reset_model->find_by_email($email);
				$now = time();
				if (!$previous || strtotime($previous['requested_at']) <= $now - 60) {
					$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
					$requested_at = date('Y-m-d H:i:s', $now);
					$expires_at = date('Y-m-d H:i:s', $now + 600);
					$this->Password_reset_model->save_otp(
						$email,
						(int) $user['id'],
						password_hash($otp, PASSWORD_DEFAULT),
						$expires_at,
						$requested_at
					);
					$body = '<p>Hello ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . ',</p>'
						. '<p>Your EcoTrip password-reset code is <strong>' . $otp . '</strong>.</p>'
						. '<p>It expires in 10 minutes. If you did not request it, you can ignore this email.</p>';
					integration_send_email($email, 'Your EcoTrip password-reset code', $body);
				}
			}
		}
		$this->session->set_flashdata('success', 'If an account matches that email, a password-reset code will be sent shortly.');
		$this->go('forgot-password');
	}

	public function reset_password() {
		$this->call->view('pages/reset_password', [
			'title' => 'Reset password',
			'flash' => $this->session->flashdata('success'),
			'flash_error' => $this->session->flashdata('error'),
		]);
	}

	public function reset_password_submit() {
		integration_require_csrf();
		$this->call->model('Password_reset_model');
		$email = strtolower(trim((string) ($_POST['email'] ?? '')));
		$otp = trim((string) ($_POST['otp'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		$confirmation = (string) ($_POST['confirm_password'] ?? '');
		$reset = filter_var($email, FILTER_VALIDATE_EMAIL)
			? $this->Password_reset_model->find_by_email($email)
			: null;
		$user = $reset ? $this->User_model->find_by_id((int) $reset['user_id']) : null;

		if (!$reset || !$user || strtotime($reset['expires_at']) < time() || (int) $reset['attempts'] >= 5
			|| !preg_match('/^\d{6}$/', $otp)
			|| !password_verify($otp, $reset['otp_hash'])) {
			if ($reset && (int) $reset['attempts'] < 5) {
				$this->Password_reset_model->increment_attempts($email);
			}
			$this->session->set_flashdata('error', 'The email or code is invalid or expired. Request a new code and try again.');
			$this->go('reset-password');
		}
		if (strlen($password) < 12 || strlen($password) > 200 || $password !== $confirmation) {
			$this->session->set_flashdata('error', 'Use a password of at least 12 characters and enter it the same way twice.');
			$this->go('reset-password');
		}

		$this->User_model->update_password((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
		$this->Password_reset_model->delete_for_email($email);
		$this->session->set_flashdata('success', 'Your password has been reset. You can now sign in.');
		$this->go('login');
	}

	public function google_start() {
		if (!integration_google_configured()) {
			$this->session->set_flashdata('error', 'Google sign-in is not configured yet.');
			$this->go('login');
		}
		$state = bin2hex(random_bytes(32));
		$nonce = bin2hex(random_bytes(32));
		$code_verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
		$code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
		$this->session->set_userdata('google_oidc_state', $state);
		$this->session->set_userdata('google_oidc_nonce', $nonce);
		$this->session->set_userdata('google_oidc_code_verifier', $code_verifier);
		$current_user = auth_current_user();
		if ($current_user) {
			$this->session->set_userdata('google_oidc_link_user_id', (int) $current_user['id']);
		} else {
			$this->session->unset_userdata('google_oidc_link_user_id');
		}
		$query = http_build_query([
			'client_id' => trim((string) config_item('google_client_id')),
			'redirect_uri' => trim((string) config_item('google_redirect_uri')),
			'response_type' => 'code',
			'scope' => 'openid email profile',
			'state' => $state,
			'nonce' => $nonce,
			'code_challenge' => $code_challenge,
			'code_challenge_method' => 'S256',
		]);
		header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query, true, 302);
		exit;
	}

	public function google_callback() {
		$state = (string) ($_GET['state'] ?? '');
		$expected_state = (string) $this->session->userdata('google_oidc_state');
		$nonce = (string) $this->session->userdata('google_oidc_nonce');
		$code_verifier = (string) $this->session->userdata('google_oidc_code_verifier');
		$link_user_id = (int) $this->session->userdata('google_oidc_link_user_id');
		$this->session->unset_userdata(['google_oidc_state', 'google_oidc_nonce', 'google_oidc_code_verifier']);
		if ($state === '' || $expected_state === '' || !hash_equals($expected_state, $state)
			|| $nonce === '' || $code_verifier === '' || !is_string($_GET['code'] ?? null)
			|| strlen($_GET['code']) > 4096) {
			$this->session->unset_userdata('google_oidc_link_user_id');
			$this->session->set_flashdata('error', 'Google sign-in could not be verified. Please try again.');
			$this->go('login');
		}

		$token_response = integration_http_request(
			'https://oauth2.googleapis.com/token',
			'POST',
			['Content-Type: application/x-www-form-urlencoded'],
			http_build_query([
				'code' => (string) $_GET['code'],
				'client_id' => trim((string) config_item('google_client_id')),
				'client_secret' => trim((string) config_item('google_client_secret')),
				'redirect_uri' => trim((string) config_item('google_redirect_uri')),
				'grant_type' => 'authorization_code',
				'code_verifier' => $code_verifier,
			]),
			15
		);
		$tokens = json_decode($token_response['body'], true);
		if ($token_response['status'] !== 200 || !is_string($tokens['id_token'] ?? null)) {
			$this->session->unset_userdata('google_oidc_link_user_id');
			error_log('Google OAuth token exchange failed with HTTP ' . $token_response['status'] . '.');
			$this->session->set_flashdata('error', 'Google sign-in could not be completed. Please try again.');
			$this->go('login');
		}

		$keys_response = integration_http_request('https://www.googleapis.com/oauth2/v3/certs', 'GET', [], null, 15);
		$key_set = json_decode($keys_response['body'], true);
		if ($keys_response['status'] !== 200 || empty($key_set['keys'])) {
			$this->session->unset_userdata('google_oidc_link_user_id');
			error_log('Google OpenID signing keys could not be retrieved.');
			$this->session->set_flashdata('error', 'Google sign-in could not be verified. Please try again.');
			$this->go('login');
		}

		try {
			$claims = \Firebase\JWT\JWT::decode(
				$tokens['id_token'],
				\Firebase\JWT\JWK::parseKeySet($key_set)
			);
		} catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $exception) {
			$this->session->unset_userdata('google_oidc_link_user_id');
			error_log('Google OpenID token signature validation failed.');
			$this->session->set_flashdata('error', 'Google sign-in could not be verified. Please try again.');
			$this->go('login');
		}

		$audiences = is_array($claims->aud ?? null) ? $claims->aud : [$claims->aud ?? ''];
		$issuer_valid = in_array($claims->iss ?? '', ['https://accounts.google.com', 'accounts.google.com'], true);
		$verified_email = ($claims->email_verified ?? false) === true;
		if (!$issuer_valid || !in_array(trim((string) config_item('google_client_id')), $audiences, true)
			|| (count($audiences) > 1 && ($claims->azp ?? '') !== trim((string) config_item('google_client_id')))
			|| empty($claims->sub) || empty($claims->email) || !$verified_email
			|| !filter_var($claims->email, FILTER_VALIDATE_EMAIL) || strlen($claims->email) > 150
			|| empty($claims->nonce) || !hash_equals($nonce, (string) $claims->nonce)) {
			$this->session->unset_userdata('google_oidc_link_user_id');
			$this->session->set_flashdata('error', 'Google sign-in could not be verified. Please try again.');
			$this->go('login');
		}

		$google_sub = (string) $claims->sub;
		$email = strtolower(trim((string) $claims->email));
		if ($link_user_id > 0) {
			$user = $this->User_model->find_by_id($link_user_id);
			$existing_identity = $this->User_model->find_by_google_sub($google_sub);
			if (!$user || ($existing_identity && (int) $existing_identity['id'] !== $link_user_id)) {
				$this->session->unset_userdata('google_oidc_link_user_id');
				$this->session->set_flashdata('error', 'That Google account cannot be linked to this EcoTrip account.');
				$this->go('account/password');
			}
			$this->User_model->link_google_sub($link_user_id, $google_sub);
			$user = $this->User_model->find_by_id($link_user_id);
			$this->session->unset_userdata('google_oidc_link_user_id');
			$this->establish_session($user);
			$this->session->set_flashdata('success', 'Google sign-in is now linked to your account.');
			$this->go('account/password');
		}
		$user = $this->User_model->find_by_google_sub($google_sub);
		if (!$user) {
			if ($this->User_model->find_by_email($email)) {
				$this->session->unset_userdata('google_oidc_link_user_id');
				$this->session->set_flashdata('error', 'This email already has an EcoTrip account. Sign in with its password, then connect Google from account settings.');
				$this->go('login');
			}
			$name = mb_substr(trim((string) ($claims->name ?? $claims->given_name ?? 'EcoTrip traveler')), 0, 100);
			$user_id = $this->User_model->create_google_user($name, $email, $google_sub);
			$user = $this->User_model->find_by_id($user_id);
		}
		$this->session->unset_userdata('google_oidc_link_user_id');
		$this->establish_session($user);
		$this->session->set_flashdata('success', 'Signed in with Google.');
		$this->go($user['role'] === 'admin' ? 'admin' : 'dashboard');
	}
}
