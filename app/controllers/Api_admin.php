<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_admin extends Api_v1 {
    public function admin_overview() {
        $this->require_admin();
        api_json_response(200, [
            'overview' => $this->Admin_model->overview(),
            'users' => $this->User_model->all_users(),
            'trips' => $this->Trip_model->all_for_admin(),
            'stays' => $this->Stay_model->all_stays(),
            'routes' => $this->Transport_model->all_options(),
            'bookings' => $this->Booking_model->all_for_admin(),
            'reminders' => $this->Reminder_model->all_for_admin(),
            'photos' => $this->Journal_model->entries_for_admin(),
        ]);
    }

    public function admin_users() {
        $this->require_admin();
        api_json_response(200, [
            'users' => array_map(fn($user) => $this->public_user($user), $this->User_model->all_users()),
        ]);
    }

    public function admin_create_user() {
        $this->require_admin();
        $body = $this->body();
        $name = trim(strip_tags((string) ($body['name'] ?? '')));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $role = (string) ($body['role'] ?? 'traveler');

        if (
            $name === ''
            || mb_strlen($name) > 100
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 12
            || strlen($password) > 200
            || !in_array($role, ['traveler', 'admin'], true)
            || $this->User_model->find_by_email($email)
        ) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Enter a valid unused user and password.');
        }

        $id = $this->User_model->create_user(
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            '',
            $role
        );
        $this->audit('user.created', 'user', $id);
        api_json_response(201, [
            'user' => $this->public_user($this->User_model->find_by_id($id)),
        ]);
    }

    public function admin_update_user_role($id) {
        $this->require_admin();
        $role = (string) ($this->body()['role'] ?? '');
        $adminId = $this->uid();
        $userId = (int) $id;

        if ($userId < 1 || !in_array($role, ['traveler', 'admin'], true) || $userId === $adminId) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'That account role cannot be changed.');
        }

        $target = $this->User_model->find_by_id($userId);
        if (!$target) {
            api_json_response(404, null, 'NOT_FOUND', 'User not found.');
        }
        if ($target['role'] === 'admin' && $role !== 'admin' && $this->User_model->count_admins() <= 1) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'The last administrator cannot be demoted.');
        }
        if ($target['role'] !== $role) {
            $this->User_model->update_role($userId, $role);
            $this->audit('user.role_changed', 'user', $userId);
        }

        api_json_response(200, [
            'user' => $this->public_user($this->User_model->find_by_id($userId)),
        ]);
    }

    public function admin_trips() {
        $this->require_admin();
        api_json_response(200, ['trips' => $this->Trip_model->all_for_admin()]);
    }

    public function admin_update_trip($id) {
        $this->require_admin();
        $tripId = (int) $id;
        $trip = $this->Trip_model->find_for_admin($tripId);
        if (!$trip) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }

        $status = (string) ($this->body()['status'] ?? '');
        if (!in_array($status, ['draft', 'planned', 'booked', 'completed'], true)) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Choose a valid trip status.');
        }
        if ($trip['status'] !== $status) {
            $this->Trip_model->set_status_for_admin($tripId, $status);
            $this->audit('trip.status_changed', 'trip', $tripId);
        }

        api_json_response(200, ['updated' => true]);
    }

    public function admin_delete_trip($id) {
        $this->require_admin();
        $tripId = (int) $id;
        if (!$this->Trip_model->find_for_admin($tripId)) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }

        $this->Trip_model->delete_for_admin($tripId);
        $this->audit('trip.deleted', 'trip', $tripId);
        api_json_response(200, ['deleted' => true]);
    }

    public function admin_stays() {
        $this->require_admin();
        api_json_response(200, ['stays' => $this->Stay_model->all_stays()]);
    }

    public function admin_save_stay() {
        $this->require_admin();
        $body = $this->body();
        $id = max(0, (int) ($body['id'] ?? 0));
        $stay = [
            'name' => trim(strip_tags((string) ($body['name'] ?? ''))),
            'location' => trim(strip_tags((string) ($body['location'] ?? ''))),
            'eco_rating' => filter_var($body['eco_rating'] ?? null, FILTER_VALIDATE_INT),
            'price_per_night' => filter_var($body['price_per_night'] ?? null, FILTER_VALIDATE_FLOAT),
            'certification' => trim(strip_tags((string) ($body['certification'] ?? ''))),
            'description' => trim(strip_tags((string) ($body['description'] ?? ''))),
        ];

        if (
            $stay['name'] === ''
            || mb_strlen($stay['name']) > 150
            || $stay['location'] === ''
            || mb_strlen($stay['location']) > 150
            || $stay['eco_rating'] === false
            || $stay['eco_rating'] < 0
            || $stay['eco_rating'] > 5
            || $stay['price_per_night'] === false
            || $stay['price_per_night'] <= 0
            || mb_strlen($stay['certification']) > 100
        ) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Check the stay name, location, certification, price, and 0–5 eco rating.');
        }
        if ($id > 0 && !$this->Stay_model->find_stay($id)) {
            api_json_response(404, null, 'NOT_FOUND', 'Stay not found.');
        }

        $savedId = $this->Stay_model->save_stay($id ?: null, $stay);
        if (!$id || $savedId > 0) {
            $this->audit($id ? 'stay.updated' : 'stay.created', 'stay', $id ?: $savedId);
        }
        api_json_response($id ? 200 : 201, ['stay_id' => $id ?: $savedId]);
    }

    public function admin_delete_stay($id) {
        $this->require_admin();
        $stayId = (int) $id;
        if (!$this->Stay_model->find_stay($stayId)) {
            api_json_response(404, null, 'NOT_FOUND', 'Stay not found.');
        }
        if (!$this->Stay_model->delete_stay($stayId)) {
            api_json_response(409, null, 'DELETE_FAILED', 'The stay could not be deleted.');
        }

        $this->audit('stay.deleted', 'stay', $stayId);
        api_json_response(200, ['deleted' => true]);
    }

    public function admin_routes() {
        $this->require_admin();
        api_json_response(200, ['routes' => $this->Transport_model->all_options()]);
    }

    public function admin_save_route() {
        $this->require_admin();
        $body = $this->body();
        $id = max(0, (int) ($body['id'] ?? 0));
        $route = [
            'mode' => (string) ($body['mode'] ?? ''),
            'origin' => trim(strip_tags((string) ($body['origin'] ?? ''))),
            'destination' => trim(strip_tags((string) ($body['destination'] ?? ''))),
            'distance_km' => filter_var($body['distance_km'] ?? null, FILTER_VALIDATE_FLOAT),
            'co2_kg_per_person' => filter_var($body['co2_kg_per_person'] ?? null, FILTER_VALIDATE_FLOAT),
            'cost' => filter_var($body['cost'] ?? null, FILTER_VALIDATE_FLOAT),
        ];

        if (
            !in_array($route['mode'], ['bus', 'train', 'bike', 'walk', 'ferry', 'carpool'], true)
            || $route['origin'] === ''
            || mb_strlen($route['origin']) > 150
            || $route['destination'] === ''
            || mb_strlen($route['destination']) > 150
            || $route['distance_km'] === false
            || $route['distance_km'] < 0
            || $route['co2_kg_per_person'] === false
            || $route['co2_kg_per_person'] < 0
            || $route['cost'] === false
            || $route['cost'] < 0
        ) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Check the route mode, locations, distance, emissions, and cost.');
        }
        if ($id > 0 && !$this->Transport_model->find_option($id)) {
            api_json_response(404, null, 'NOT_FOUND', 'Route not found.');
        }

        $savedId = $this->Transport_model->save_option($id ?: null, $route);
        if (!$id || $savedId > 0) {
            $this->audit($id ? 'route.updated' : 'route.created', 'route', $id ?: $savedId);
        }
        api_json_response($id ? 200 : 201, ['route_id' => $id ?: $savedId]);
    }

    public function admin_delete_route($id) {
        $this->require_admin();
        $routeId = (int) $id;
        if (!$this->Transport_model->find_option($routeId)) {
            api_json_response(404, null, 'NOT_FOUND', 'Route not found.');
        }
        if (!$this->Transport_model->delete_option($routeId)) {
            api_json_response(409, null, 'DELETE_FAILED', 'The route could not be deleted.');
        }

        $this->audit('route.deleted', 'route', $routeId);
        api_json_response(200, ['deleted' => true]);
    }

    public function admin_bookings() {
        $this->require_admin();
        api_json_response(200, ['bookings' => $this->Booking_model->all_for_admin()]);
    }

    public function admin_reconcile_booking($id) {
        $this->require_admin();
        $booking = $this->Booking_model->find_for_admin((int) $id);
        if (!$booking || $booking['payment_status'] !== 'pending') {
            api_json_response(404, null, 'NOT_FOUND', 'Pending booking not found.');
        }

        $reference = (string) $booking['paymongo_ref'];
        $paid = str_starts_with($reference, 'cs_')
            ? integration_paymongo_checkout_paid($reference, $booking['amount'])
            : integration_paymongo_link_paid($reference, $booking['amount']);
        if (!$paid) {
            api_json_response(409, null, 'PAYMENT_NOT_CONFIRMED', 'PayMongo has not confirmed a matching paid payment.');
        }

        $this->Booking_model->set_status_by_reference($reference, 'paid');
        $this->audit('booking.reconciled', 'booking', (int) $id);
        api_json_response(200, ['reconciled' => true]);
    }

    public function admin_reminders() {
        $this->require_admin();
        api_json_response(200, ['reminders' => $this->Reminder_model->all_for_admin()]);
    }

    public function admin_photos() {
        $this->require_admin();
        api_json_response(200, ['photos' => $this->Journal_model->entries_for_admin()]);
    }

    private function audit($action, $entityType, $entityId) {
        api_audit_log($this->db, $this->uid(), $action, $entityType, (int) $entityId);
    }
}
