<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Dashboard extends Controller {
    public function __construct() { parent::__construct(); $this->call->model('Trip_model'); }

    public function index() {
        auth_require_login();
        $user = auth_current_user();
        if (($user['role'] ?? '') === 'admin') { redirect('admin'); return; }
        $uid = auth_user_id();
        $this->call->view('pages/dashboard', [
            'title'    => 'Dashboard',
            'stats'    => $this->Trip_model->stats($uid),
            'upcoming' => $this->Trip_model->upcoming($uid),
        ]);
    }
}
