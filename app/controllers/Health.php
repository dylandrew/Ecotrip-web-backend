<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Health extends Controller {
    public function index() {
        $healthy = true;
        try {
            $this->db->table('users')->select('id')->limit(1)->get();
        } catch (PDOException) {
            error_log('Health check database probe failed.');
            $healthy = false;
        }

        http_response_code($healthy ? 200 : 503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $healthy ? 'ok' : 'unavailable',
            'database' => $healthy ? 'connected' : 'unavailable',
        ]);
    }
}
