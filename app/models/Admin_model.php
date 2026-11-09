<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Admin_model extends Model {
    public function overview() {
        return [
            'users' => $this->db->table('users')->count(),
            'admins' => $this->db->table('users')->where('role', 'admin')->count(),
            'trips' => $this->db->table('trips')->count(),
            'stays' => $this->db->table('stays')->count(),
            'routes' => $this->db->table('transport_options')->count(),
            'pending_payments' => $this->db->table('bookings')->where('payment_status', 'pending')->count(),
            'photos' => $this->db->table('journal_entries')->count(),
            'pending_reminders' => $this->db->table('reminders')->where('status', 'pending')->count(),
        ];
    }
}