<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Admin extends Controller {
    public function __construct() {
        parent::__construct();
        auth_require_admin();
        $this->call->model('Admin_model');
        $this->call->model('User_model');
        $this->call->model('Trip_model');
        $this->call->model('Stay_model');
        $this->call->model('Transport_model');
        $this->call->model('Booking_model');
        $this->call->model('Reminder_model');
        $this->call->model('Journal_model');
    }

    private function go($path = 'admin') { redirect($path); exit; }
    private function audit($action, $targetType = null, $targetId = null) {
        $this->db->table('audit_logs')->insert([
            'user_id' => auth_user_id(),
            'action' => mb_substr($action, 0, 100),
            'target_type' => $targetType === null ? null : mb_substr($targetType, 0, 50),
            'target_id' => $targetId === null ? null : (int) $targetId,
            'ip' => integration_client_ip(),
        ]);
    }

    public function index() {
        $this->call->view('pages/admin', [
            'title' => 'Admin',
            'overview' => $this->Admin_model->overview(),
            'users' => $this->User_model->all_users(),
            'trips' => $this->Trip_model->all_for_admin(),
            'stays' => $this->Stay_model->all_stays(),
            'routes' => $this->Transport_model->all_options(),
            'bookings' => $this->Booking_model->all_for_admin(),
            'reminders' => $this->Reminder_model->all_for_admin(),
            'photos' => $this->Journal_model->entries_for_admin(),
            'current_user_id' => auth_user_id(),
            'flash' => $this->session->flashdata('success'),
            'flash_error' => $this->session->flashdata('error'),
        ]);
    }

    public function create_user() {
        integration_require_csrf();
        $name = trim(strip_tags((string) ($_POST['name'] ?? '')));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'traveler');
        if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150 || strlen($password) < 12 || strlen($password) > 200 || !in_array($role, ['traveler', 'admin'], true)) {
            $this->session->set_flashdata('error', 'Enter a valid name and email; passwords must be at least 12 characters.');
            $this->go();
        }
        if ($this->User_model->find_by_email($email)) {
            $this->session->set_flashdata('error', 'An account with that email already exists.');
            $this->go();
        }
        $newId = $this->User_model->create_user($name, $email, password_hash($password, PASSWORD_DEFAULT), '', $role);
        $this->audit('user.created', 'user', (int) $newId);
        $this->session->set_flashdata('success', 'Account created. The user can sign in and change their password.');
        $this->go();
    }

    public function update_role($id) {
        integration_require_csrf();
        $id = (int) $id;
        $role = (string) ($_POST['role'] ?? '');
        $target = $this->User_model->find_by_id($id);
        if (!$target || !in_array($role, ['traveler', 'admin'], true) || $id === auth_user_id()) {
            $this->session->set_flashdata('error', 'That account role cannot be changed.');
            $this->go();
        }
        if ($target['role'] === 'admin' && $role !== 'admin' && $this->User_model->count_admins() <= 1) {
            $this->session->set_flashdata('error', 'The last administrator cannot be demoted.');
            $this->go();
        }
        $this->User_model->update_role($id, $role);
        $this->audit('user.role_changed', 'user', $id);
        $this->session->set_flashdata('success', 'Account role updated.');
        $this->go();
    }

    public function update_trip_status($id) {
        integration_require_csrf();
        $status = (string) ($_POST['status'] ?? '');
        if (!$this->Trip_model->set_status_for_admin((int) $id, $status)) {
            $this->session->set_flashdata('error', 'Choose a valid trip status.');
            $this->go();
        }
        $this->audit('trip.status_changed', 'trip', (int) $id);
        $this->session->set_flashdata('success', 'Trip status updated.');
        $this->go();
    }

    public function delete_trip($id) {
        integration_require_csrf();
        $this->Trip_model->delete_for_admin((int) $id);
        $this->audit('trip.deleted', 'trip', (int) $id);
        $this->session->set_flashdata('success', 'Trip and its dependent records were deleted.');
        $this->go();
    }

    public function reconcile_booking($id) {
        integration_require_csrf();
        $booking = $this->Booking_model->find_for_admin((int) $id);
        if (!$booking || $booking['payment_status'] !== 'pending'
            || !integration_paymongo_link_paid($booking['paymongo_ref'], $booking['amount'])) {
            $this->session->set_flashdata('error', 'PayMongo has not confirmed a matching paid checkout for that pending booking.');
            $this->go();
        }
        $this->Booking_model->set_status_by_reference($booking['paymongo_ref'], 'paid');
        $this->audit('booking.reconciled', 'booking', (int) $id);
        $this->session->set_flashdata('success', 'PayMongo confirmed the booking amount and payment.');
        $this->go();
    }

    public function save_stay() {
        integration_require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $stay = [
            'name' => trim(strip_tags((string) ($_POST['name'] ?? ''))),
            'location' => trim(strip_tags((string) ($_POST['location'] ?? ''))),
            'eco_rating' => filter_var($_POST['eco_rating'] ?? null, FILTER_VALIDATE_INT),
            'price_per_night' => filter_var($_POST['price_per_night'] ?? null, FILTER_VALIDATE_FLOAT),
            'certification' => trim(strip_tags((string) ($_POST['certification'] ?? ''))),
            'description' => trim(strip_tags((string) ($_POST['description'] ?? ''))),
        ];
        if ($stay['name'] === '' || mb_strlen($stay['name']) > 150 || $stay['location'] === '' || mb_strlen($stay['location']) > 150 || $stay['eco_rating'] === false || $stay['eco_rating'] < 0 || $stay['eco_rating'] > 5 || $stay['price_per_night'] === false || $stay['price_per_night'] <= 0 || mb_strlen($stay['certification']) > 100) {
            $this->session->set_flashdata('error', 'Check the stay name, location, 0–5 eco rating, and nightly price. Use 0 for a stay without an eco rating.');
            $this->go();
        }
        $this->Stay_model->save_stay($id ?: null, $stay);
        $this->audit($id ? 'stay.updated' : 'stay.created', 'stay', $id ?: null);
        $this->session->set_flashdata('success', $id ? 'Stay updated.' : 'Stay added.');
        $this->go();
    }

    public function delete_stay($id) {
        integration_require_csrf();
        $this->Stay_model->delete_stay((int) $id);
        $this->audit('stay.deleted', 'stay', (int) $id);
        $this->session->set_flashdata('success', 'Stay removed from the directory.');
        $this->go();
    }

    public function save_route() {
        integration_require_csrf();
        $id = (int) ($_POST['id'] ?? 0);
        $route = [
            'mode' => (string) ($_POST['mode'] ?? ''),
            'origin' => trim(strip_tags((string) ($_POST['origin'] ?? ''))),
            'destination' => trim(strip_tags((string) ($_POST['destination'] ?? ''))),
            'distance_km' => filter_var($_POST['distance_km'] ?? null, FILTER_VALIDATE_FLOAT),
            'co2_kg_per_person' => filter_var($_POST['co2_kg_per_person'] ?? null, FILTER_VALIDATE_FLOAT),
            'cost' => filter_var($_POST['cost'] ?? null, FILTER_VALIDATE_FLOAT),
        ];
        if (!in_array($route['mode'], ['bus', 'train', 'bike', 'walk', 'ferry', 'carpool'], true) || $route['origin'] === '' || mb_strlen($route['origin']) > 150 || $route['destination'] === '' || mb_strlen($route['destination']) > 150 || $route['distance_km'] === false || $route['distance_km'] < 0 || $route['co2_kg_per_person'] === false || $route['co2_kg_per_person'] < 0 || $route['cost'] === false || $route['cost'] < 0) {
            $this->session->set_flashdata('error', 'Check the route mode, locations, distance, emissions, and cost.');
            $this->go();
        }
        $this->Transport_model->save_option($id ?: null, $route);
        $this->audit($id ? 'route.updated' : 'route.created', 'route', $id ?: null);
        $this->session->set_flashdata('success', $id ? 'Route updated.' : 'Route added.');
        $this->go();
    }

    public function delete_route($id) {
        integration_require_csrf();
        $this->Transport_model->delete_option((int) $id);
        $this->audit('route.deleted', 'route', (int) $id);
        $this->session->set_flashdata('success', 'Route removed from the directory.');
        $this->go();
    }
}