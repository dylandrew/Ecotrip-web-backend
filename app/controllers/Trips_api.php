<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Trips_api extends Controller {
    private const STATUSES = ['draft', 'planned', 'booked', 'completed'];

    private $api;

    public function __construct() {
        parent::__construct();
        $this->api = $this->call->library('api');
        $this->call->model('Trip_model');
    }

    public function session_token() {
        $this->api->require_method('GET');
        $user = auth_current_user();
        if (!is_array($user) || (int) ($user['id'] ?? 0) < 1) {
            $this->api->respond_error('Sign in to manage trips.', 401);
        }

        $token = $this->api->encode_jwt([
            'sub' => (int) $user['id'],
            'role' => $user['role'] ?? 'traveler',
            'scopes' => ['read', 'write', 'delete'],
        ]);
        $this->api->respond([
            'access_token' => $token,
            'expires_in' => (int) config_item('payload_token_expiration'),
            'token_type' => 'Bearer',
        ]);
    }

    public function index() {
        $this->api->require_method('GET');
        $claims = $this->api->require_jwt();
        $query = $this->api->get_query_params();
        $search = trim((string) ($query['q'] ?? ''));
        $this->api->respond([
            'trips' => $this->Trip_model->all_for_user((int) $claims['sub'], $search),
        ]);
    }

    public function show($id) {
        $this->api->require_method('GET');
        $claims = $this->api->require_jwt();
        $trip = $this->Trip_model->find_for_user((int) $id, (int) $claims['sub']);
        if (!$trip) $this->api->respond_error('Trip not found.', 404);
        $this->api->respond([
            'trip' => $trip,
            'items' => $this->Trip_model->items((int) $id),
        ]);
    }

    public function store() {
        $this->api->require_method('POST');
        $claims = $this->api->require_jwt();
        $trip = $this->validated_trip($this->api->body());
        if (isset($trip['error'])) $this->api->respond_error($trip['error'], 422);

        $trip['user_id'] = (int) $claims['sub'];
        $id = $this->Trip_model->create_trip($trip);
        $this->api->respond([
            'trip' => $this->Trip_model->find_for_user($id, $trip['user_id']),
        ], 201);
    }

    public function update($id) {
        $this->api->require_method('PUT');
        $claims = $this->api->require_jwt();
        $userId = (int) $claims['sub'];
        if (!$this->Trip_model->find_for_user((int) $id, $userId)) {
            $this->api->respond_error('Trip not found.', 404);
        }

        $trip = $this->validated_trip($this->api->body());
        if (isset($trip['error'])) $this->api->respond_error($trip['error'], 422);

        $this->Trip_model->update_trip((int) $id, $userId, $trip);
        $this->api->respond([
            'trip' => $this->Trip_model->find_for_user((int) $id, $userId),
        ]);
    }

    public function delete($id) {
        $this->api->require_method('DELETE');
        $claims = $this->api->require_jwt();
        $userId = (int) $claims['sub'];
        if (!$this->Trip_model->find_for_user((int) $id, $userId)) {
            $this->api->respond_error('Trip not found.', 404);
        }

        $this->Trip_model->delete_trip((int) $id, $userId);
        $this->api->respond(['deleted' => true]);
    }

    private function validated_trip(array $input) {
        $text = static function ($value) {
            return is_string($value) ? trim(htmlspecialchars_decode($value, ENT_QUOTES)) : '';
        };
        $title = $text($input['title'] ?? null);
        $destination = $text($input['destination'] ?? null);
        $start = $text($input['start_date'] ?? null);
        $end = $text($input['end_date'] ?? null);
        $status = $text($input['status'] ?? 'draft');
        $budget = filter_var($input['budget'] ?? null, FILTER_VALIDATE_FLOAT);
        $travelers = filter_var($input['travelers'] ?? null, FILTER_VALIDATE_INT);

        if ($title === '' || mb_strlen($title) > 150) {
            return ['error' => 'Enter a trip title of 1 to 150 characters.'];
        }
        if ($destination === '' || mb_strlen($destination) > 150) {
            return ['error' => 'Enter a destination of 1 to 150 characters.'];
        }
        if (!$this->valid_date($start) || !$this->valid_date($end) || $end < $start) {
            return ['error' => 'Enter valid trip dates and ensure the end date is not before the start date.'];
        }
        if ($budget === false || !is_finite((float) $budget) || $budget <= 0 || $budget > 99999999.99) {
            return ['error' => 'Enter a budget greater than 0 and within the supported range.'];
        }
        if ($travelers === false || $travelers < 1 || $travelers > 50) {
            return ['error' => 'Travelers must be a whole number from 1 to 50.'];
        }
        if (!in_array($status, self::STATUSES, true)) {
            return ['error' => 'Choose a valid trip status.'];
        }

        return [
            'title' => $title,
            'destination' => $destination,
            'start_date' => $start,
            'end_date' => $end,
            'budget' => $budget,
            'travelers' => $travelers,
            'status' => $status,
        ];
    }

    private function valid_date($value) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }
}
