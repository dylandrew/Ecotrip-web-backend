<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Password_reset_model extends Model {
    protected $table = 'password_reset_otps';

    public function find_by_email($email) {
        return $this->db->table($this->table)->where('email', $email)->get();
    }

    public function save_otp($email, $user_id, $otp_hash, $expires_at, $requested_at) {
        $existing = $this->find_by_email($email);
        $fields = [
            'user_id' => $user_id,
            'otp_hash' => $otp_hash,
            'expires_at' => $expires_at,
            'requested_at' => $requested_at,
            'attempts' => 0,
        ];
        if ($existing) {
            return $this->db->table($this->table)->where('email', $email)->update($fields);
        }
        return $this->db->table($this->table)->insert(['email' => $email] + $fields);
    }

    public function increment_attempts($email) {
        return $this->db->table($this->table)
            ->where('email', $email)
            ->where('attempts', '<', 5)
            ->increment('attempts');
    }

    public function delete_for_email($email) {
        return $this->db->table($this->table)->where('email', $email)->delete();
    }
}
