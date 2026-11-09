<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

// ---- JSON API v1 ----
$router->get('/', 'Api_v1::health');
$router->get('/api/v1/health', 'Api_v1::health');
$router->post('/api/v1/auth/register', 'Api_auth::register');
$router->post('/api/v1/auth/login', 'Api_auth::login');
$router->post('/api/v1/auth/logout', 'Api_auth::logout');
$router->post('/api/v1/auth/refresh', 'Api_auth::refresh');
$router->post('/api/v1/auth/forgot-password', 'Api_auth::forgot_password');
$router->post('/api/v1/auth/reset-password', 'Api_auth::reset_password');
$router->post('/api/v1/auth/change-password', 'Api_auth::change_password');
$router->put('/api/v1/auth/address', 'Api_auth::update_address');
$router->get('/api/v1/auth/google/start', 'Api_auth::google_start');
$router->get('/api/v1/auth/google/callback', 'Api_auth::google_callback');
$router->post('/api/v1/auth/google/exchange', 'Api_auth::google_exchange');
$router->get('/api/v1/account/export', 'Api_account::account_export');
$router->post('/api/v1/account/delete', 'Api_account::account_delete');
$router->get('/api/v1/trips', 'Api_trips::trips_index');
$router->post('/api/v1/trips', 'Api_trips::trips_create');
$router->post('/api/v1/trips/generate-itinerary', 'Api_trips::generate_itinerary');
$router->get('/api/v1/trips/{id}', 'Api_trips::trips_show')->where_number('id');
$router->put('/api/v1/trips/{id}', 'Api_trips::trips_update')->where_number('id');
$router->patch('/api/v1/trips/{id}', 'Api_trips::trips_update')->where_number('id');
$router->put('/api/v1/trips/{id}/experience', 'Api_trips::trip_experience_update')->where_number('id');
$router->put('/api/v1/trips/{id}/items/{item_id}', 'Api_trips::itinerary_item_update')->where_number('id')->where_number('item_id');
$router->delete('/api/v1/trips/{id}', 'Api_trips::trips_delete')->where_number('id');
$router->get('/api/v1/experiences', 'Api_trips::shared_experiences');
$router->get('/api/v1/stays', 'Api_stays::stays_index');
$router->get('/api/v1/stays/nearby', 'Api_stays::stays_nearby');
$router->get('/api/v1/stays/{id}', 'Api_stays::stays_show')->where_number('id');
$router->post('/api/v1/stays/{id}/checkout', 'Api_stays::stays_checkout')->where_number('id');
$router->post('/api/v1/stays/nearby/checkout', 'Api_stays::stays_checkout');
$router->get('/api/v1/transport', 'Api_transport::transport_index');
$router->get('/api/v1/bookings', 'Api_bookings::bookings_list');
$router->post('/api/v1/bookings/checkout', 'Api_bookings::bookings_checkout');
$router->post('/api/v1/bookings/{id}/verify', 'Api_bookings::bookings_verify')->where_number('id');
$router->get('/api/v1/bookings/{id}/receipt', 'Api_bookings::bookings_receipt')->where_number('id');
$router->post('/api/v1/webhooks/paymongo', 'Api_bookings::paymongo_webhook');
$router->get('/api/v1/journal', 'Api_journal::journal_list');
$router->post('/api/v1/journal/upload', 'Api_journal::journal_upload');
$router->put('/api/v1/journal/{id}', 'Api_journal::journal_update')->where_number('id');
$router->delete('/api/v1/journal/{id}', 'Api_journal::journal_delete')->where_number('id');
$router->get('/api/v1/reminders', 'Api_reminders::reminders_list');
$router->post('/api/v1/reminders', 'Api_reminders::reminders_create');
$router->put('/api/v1/reminders/{id}', 'Api_reminders::reminders_update')->where_number('id');
$router->delete('/api/v1/reminders/{id}', 'Api_reminders::reminders_delete')->where_number('id');
$router->get('/api/v1/dashboard', 'Api_dashboard::dashboard');
$router->post('/api/v1/assistant/message', 'Api_dashboard::assistant');
$router->get('/api/v1/admin', 'Api_admin::admin_overview');
$router->get('/api/v1/admin/users', 'Api_admin::admin_users');
$router->post('/api/v1/admin/users', 'Api_admin::admin_create_user');
$router->patch('/api/v1/admin/users/{id}/role', 'Api_admin::admin_update_user_role')->where_number('id');
$router->get('/api/v1/admin/trips', 'Api_admin::admin_trips');
$router->patch('/api/v1/admin/trips/{id}', 'Api_admin::admin_update_trip')->where_number('id');
$router->delete('/api/v1/admin/trips/{id}', 'Api_admin::admin_delete_trip')->where_number('id');
$router->get('/api/v1/admin/stays', 'Api_admin::admin_stays');
$router->post('/api/v1/admin/stays', 'Api_admin::admin_save_stay');
$router->delete('/api/v1/admin/stays/{id}', 'Api_admin::admin_delete_stay')->where_number('id');
$router->get('/api/v1/admin/routes', 'Api_admin::admin_routes');
$router->post('/api/v1/admin/routes', 'Api_admin::admin_save_route');
$router->delete('/api/v1/admin/routes/{id}', 'Api_admin::admin_delete_route')->where_number('id');
$router->get('/api/v1/admin/bookings', 'Api_admin::admin_bookings');
$router->post('/api/v1/admin/bookings/{id}/reconcile', 'Api_admin::admin_reconcile_booking')->where_number('id');
$router->get('/api/v1/admin/reminders', 'Api_admin::admin_reminders');
$router->get('/api/v1/admin/photos', 'Api_admin::admin_photos');
