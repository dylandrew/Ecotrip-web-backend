<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth_code_model extends Model {
    protected $table = 'api_auth_codes';

    public function create_code($user_id, $code_hash, $expires_at) {
        return $this->db->table($this->table)->insert([
            'user_id' => (int) $user_id,
            'code_hash' => $code_hash,
            'expires_at' => $expires_at,
        ]);
    }

    public function consume_code($code_hash) {
        if (!$this->db->transaction()) {
            throw new RuntimeException('Could not start the Google sign-in code transaction.');
        }

        $transaction_open = true;
        try {
            $code = $this->db->table($this->table)
                ->select('user_id')
                ->where('code_hash', $code_hash)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->limit(1)
                ->get();

            if (!$code) {
                if (!$this->db->roll_back()) {
                    throw new RuntimeException('Could not roll back the Google sign-in code transaction.');
                }
                $transaction_open = false;
                return null;
            }

            $deleted = $this->db->table($this->table)
                ->where('code_hash', $code_hash)
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->delete();

            if ($deleted !== 1) {
                if (!$this->db->roll_back()) {
                    throw new RuntimeException('Could not roll back the Google sign-in code transaction.');
                }
                $transaction_open = false;
                return null;
            }

            if (!$this->db->commit()) {
                throw new RuntimeException('Could not commit the Google sign-in code transaction.');
            }
            $transaction_open = false;
            return (int) $code['user_id'];
        } catch (Throwable $error) {
            if ($transaction_open) {
                $this->db->roll_back();
            }
            throw $error;
        }
    }
}
