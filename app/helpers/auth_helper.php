<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

function auth_current_user() {
    $user = $_SESSION['ecotrip_user'] ?? null;
    if (!is_array($user) || (int) ($user['id'] ?? 0) < 1) return null;
    return $user;
}

function auth_user_id() {
    return (int) (auth_current_user()['id'] ?? 0);
}

function auth_require_login() {
    if (auth_current_user()) return;
    if (isset(lava_instance()->session)) {
        lava_instance()->session->set_flashdata('error', 'Sign in to continue.');
        if (!empty($_SERVER['REQUEST_URI'])) {
            $return_to = '/' . ltrim((string) $_SERVER['REQUEST_URI'], '/');
            $_SESSION['auth_return_to'] = $return_to;
            lava_instance()->session->set_userdata('auth_return_to', $return_to);
        }
    }
    redirect('login');
    exit;
}

function auth_require_admin() {
    auth_require_login();
    if ((auth_current_user()['role'] ?? '') === 'admin') return;
    http_response_code(403);
    exit('Administrator access required.');
}

function auth_require_csrf() {
    integration_require_csrf();
}