<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Refresh_token_model extends Model {
    protected $table = 'refresh_tokens';

    public function exists_for_hash($token_hash) {
        return (bool) $this->db->table($this->table)
            ->select('id')
            ->where('token', $token_hash)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->limit(1)
            ->get();
    }
}
