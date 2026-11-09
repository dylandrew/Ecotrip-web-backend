<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Journal_model extends Model {
    protected $table = 'journal_entries';

    public function trips_for_user($user_id) {
        return $this->db->table('trips')
            ->select('id, title, destination')
            ->where('user_id', $user_id)
            ->order_by('start_date', 'DESC')
            ->get_all();
    }

    public function entries_for_user($user_id) {
        return $this->db->table('journal_entries j')
            ->select('j.id, j.trip_id, j.photo_path, j.caption, j.taken_at, t.title AS trip_title')
            ->join('trips t', 't.id = j.trip_id')
            ->where('t.user_id', $user_id)
            ->order_by('j.taken_at', 'DESC')
            ->order_by('j.id', 'DESC')
            ->get_all();
    }

    public function entries_for_admin() {
        return $this->db->table('journal_entries j')
            ->select('j.id, j.trip_id, j.photo_path, j.caption, j.taken_at, t.title AS trip_title, u.name AS traveler_name')
            ->join('trips t', 't.id = j.trip_id')
            ->join('users u', 'u.id = t.user_id')
            ->order_by('j.taken_at', 'DESC')
            ->order_by('j.id', 'DESC')
            ->get_all();
    }

    public function trip_belongs_to_user($trip_id, $user_id) {
        return $this->db->table('trips')
            ->select('id')
            ->where('id', $trip_id)
            ->where('user_id', $user_id)
            ->get() !== false;
    }

    public function find_owned_entry($entry_id, $user_id) {
        return $this->db->table('journal_entries j')
            ->select('j.id, j.photo_path, j.caption')
            ->join('trips t', 't.id = j.trip_id')
            ->where('j.id', $entry_id)
            ->where('t.user_id', $user_id)
            ->limit(1)
            ->get();
    }

    public function update_caption($entry_id, $user_id, $caption) {
        $entry = $this->find_owned_entry($entry_id, $user_id);
        if (!$entry) return false;
        if ($entry['caption'] === $caption) return true;

        return $this->db->table($this->table)
            ->where('id', $entry_id)
            ->update(['caption' => $caption]) > 0;
    }

    public function delete_entry($entry_id, $user_id) {
        $entry = $this->find_owned_entry($entry_id, $user_id);
        if (!$entry) return false;
        $this->db->table('journal_entries')->where('id', $entry_id)->delete();
        return $entry;
    }

    public function create_entry($trip_id, $photo_path, $caption) {
        return $this->db->table('journal_entries')->insert([
            'trip_id' => $trip_id,
            'photo_path' => $photo_path,
            'caption' => $caption,
            'taken_at' => date('Y-m-d H:i:s'),
        ]);
    }
}