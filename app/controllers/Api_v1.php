<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_v1 extends Controller {
    protected $api;
    public function __construct() {
        parent::__construct();
        api_cors_headers();
        if ($this->request->method() === 'OPTIONS') {
            http_response_code(204); exit;
        }
        $this->api = $this->call->library('api');
    }
    protected function claims($accessRequired = true) {
        $token = api_bearer_token();
        $claims = $token !== '' ? $this->api->validate_jwt($token, 'access') : false;
        if (!$claims && $accessRequired) api_json_response(401, null, 'UNAUTHORIZED', 'A valid bearer access token is required.');
        return $claims ?: null;
    }
    protected function uid($required = true) { $claims = $this->claims($required); return $claims ? (int) $claims['sub'] : null; }
    protected function require_admin() {
        $claims = $this->claims();
        $user = $this->User_model->find_by_id((int) $claims['sub']);
        if (($claims['role'] ?? '') !== 'admin' || !$user || ($user['role'] ?? '') !== 'admin') {
            api_json_response(403, null, 'FORBIDDEN', 'Administrator access required.');
        }
    }
    protected function body() { return api_json_body(); }
    protected function public_user($user) {
        if (!is_array($user)) return null;
        unset($user['password_hash'], $user['google_sub']);
        return $user;
    }

    protected function consume_rate_limit($scope, $identity, $limit, $window, $message) {
        $rate = integration_rate_limit_check($this->db, $scope, $identity, $limit, $window);
        if (!empty($rate['unavailable'])) api_json_response(503, null, 'RATE_LIMIT_UNAVAILABLE', 'Request throttling is temporarily unavailable.');
        if (!$rate['allowed']) {
            header('Retry-After: ' . max(1, (int) $rate['retry_after']));
            api_json_response(429, null, 'RATE_LIMITED', $message);
        }
        if (!integration_rate_limit_hit($this->db, $scope, $identity, $window)) {
            api_json_response(503, null, 'RATE_LIMIT_UNAVAILABLE', 'Request throttling is temporarily unavailable.');
        }
    }
    public function health() {
        try {
            $this->db->table('users')->select('id')->limit(1)->get();
        } catch (PDOException) {
            error_log('API health check database probe failed.');
            api_json_response(503, ['status' => 'unavailable', 'database' => 'unavailable'], 'DATABASE_UNAVAILABLE', 'Database connection is unavailable.');
        }

        api_json_response(200, ['status' => 'ok', 'database' => 'connected']);
    }
}
