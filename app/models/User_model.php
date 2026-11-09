<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class User_model extends Model {
    protected $table = 'users';

    public function find_by_email($email) {
        return $this->db->table($this->table)
            ->select('id, name, email, password_hash, google_sub, phone, address, role')
            ->where('email', $email)
            ->limit(1)
            ->get();
    }

    public function find_by_id($id) {
        return $this->db->table($this->table)
            ->select('id, name, email, password_hash, google_sub, phone, address, role')
            ->where('id', $id)
            ->limit(1)
            ->get();
    }

    public function find_by_google_sub($google_sub) {
        return $this->db->table($this->table)
            ->select('id, name, email, password_hash, google_sub, phone, address, role')
            ->where('google_sub', $google_sub)
            ->limit(1)
            ->get();
    }

    public function all_users() {
        return $this->db->table($this->table)
            ->select('id, name, email, phone, role, created_at')
            ->order_by('created_at', 'DESC')
            ->get_all();
    }

    public function create_user($name, $email, $password_hash, $phone = '', $role = 'traveler', $address = '') {
        $this->db->table($this->table)->insert([
            'name' => $name,
            'email' => $email,
            'password_hash' => $password_hash,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $address !== '' ? $address : null,
            'role' => $role,
        ]);
        return $this->db->last_id();
    }

    public function create_google_user($name, $email, $google_sub) {
        $this->db->table($this->table)->insert([
            'name' => $name,
            'email' => $email,
            'password_hash' => null,
            'google_sub' => $google_sub,
            'phone' => null,
            'address' => null,
            'role' => 'traveler',
        ]);
        return $this->db->last_id();
    }

    public function link_google_sub($id, $google_sub) {
        return $this->db->table($this->table)
            ->where('id', $id)
            ->update(['google_sub' => $google_sub]);
    }

    public function update_password($id, $password_hash) {
        return $this->db->table($this->table)->where('id', $id)->update(['password_hash' => $password_hash]);
    }

    public function update_phone($id, $phone) {
        return $this->db->table($this->table)->where('id', $id)->update(['phone' => $phone]);
    }

    public function update_address($id, $address) {
        return $this->db->table($this->table)->where('id', $id)->update(['address' => $address !== '' ? $address : null]);
    }

    public function update_role($id, $role) {
        if (!in_array($role, ['traveler', 'admin'], true)) return false;
        return $this->db->table($this->table)->where('id', $id)->update(['role' => $role]);
    }

    public function count_admins() {
        return (int) $this->db->table($this->table)->where('role', 'admin')->count();
    }

    public function delete_user($id) {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }

    public function export_data($id) {
        $profile = $this->find_by_id($id);
        if (is_array($profile)) unset($profile['password_hash'], $profile['google_sub']);
        return [
            'profile' => $profile,
            'trips' => $this->db->table('trips')->where('user_id', $id)->get_all(),
            'bookings' => $this->db->table('bookings b')
                ->select('b.id, b.trip_id, b.stay_id, b.reservation_name, b.amount, b.offset_amount, b.paymongo_ref, b.payment_status, b.created_at')
                ->join('trips t', 't.id = b.trip_id')
                ->where('t.user_id', $id)
                ->get_all(),
        ];
    }
}