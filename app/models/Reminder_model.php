<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Reminder_model extends Model {
    protected $table = 'reminders';

    public function trips_for_user($user_id) {
        return $this->db->table('trips')
            ->select('id, title, destination')
            ->where('user_id', $user_id)
            ->order_by('start_date')
            ->get_all();
    }

    public function belongs_to_user($trip_id, $user_id) {
        return $this->db->table('trips')
            ->select('id')
            ->where('id', $trip_id)
            ->where('user_id', $user_id)
            ->get() !== false;
    }

    public function all_for_user($user_id) {
        return $this->db->table('reminders r')
            ->select('r.id, r.trip_id, r.message, r.send_at, r.status, t.title AS trip_title')
            ->join('trips t', 't.id = r.trip_id')
            ->where('t.user_id', $user_id)
            ->order_by('r.send_at', 'DESC')
            ->get_all();
    }

    public function find_for_user($id, $user_id) {
        return $this->db->table('reminders r')
            ->select('r.id, r.trip_id, r.message, r.send_at, r.status')
            ->join('trips t', 't.id = r.trip_id')
            ->where('r.id', $id)
            ->where('t.user_id', $user_id)
            ->limit(1)
            ->get();
    }

    public function all_for_admin() {
        return $this->db->table('reminders r')
            ->select('r.id, r.trip_id, r.message, r.send_at, r.status, t.title AS trip_title, u.name AS traveler_name')
            ->join('trips t', 't.id = r.trip_id')
            ->join('users u', 'u.id = t.user_id')
            ->order_by('r.send_at', 'DESC')
            ->get_all();
    }

    public function create_reminder($trip_id, $message, $send_at) {
        $this->db->table('reminders')->insert([
            'trip_id' => $trip_id,
            'message' => $message,
            'send_at' => $send_at,
            'status' => 'pending',
        ]);
        return $this->db->last_id();
    }

    public function update_for_user($id, $user_id, $trip_id, $message, $send_at) {
        $reminder = $this->find_for_user($id, $user_id);
        if (!$reminder || $reminder['status'] !== 'pending') return false;
        if (
            (int) $reminder['trip_id'] === (int) $trip_id
            && $reminder['message'] === $message
            && $reminder['send_at'] === $send_at
        ) {
            return true;
        }

        return $this->db->table($this->table)
            ->where('id', $id)
            ->where('trip_id', $reminder['trip_id'])
            ->where('status', 'pending')
            ->update([
                'trip_id' => $trip_id,
                'message' => $message,
                'send_at' => $send_at,
            ]) > 0;
    }

    public function delete_for_user($id, $user_id) {
        $reminder = $this->find_for_user($id, $user_id);
        if (!$reminder || $reminder['status'] !== 'pending') return false;

        return $this->db->table($this->table)
            ->where('id', $id)
            ->where('trip_id', $reminder['trip_id'])
            ->where('status', 'pending')
            ->delete();
    }
}