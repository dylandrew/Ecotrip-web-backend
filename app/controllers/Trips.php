<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Trips extends Controller {
    const STATUSES = ['draft', 'planned', 'booked', 'completed'];

    public function __construct() {
        parent::__construct();
        auth_require_login();
        $this->call->model('Trip_model');
        $this->call->model('User_model');
    }

    private function uid() { return auth_user_id(); }

    // LavaLust's built-in CSRF check does nothing unless csrf_exclude_uris is non-empty,
    // so we verify the token ourselves: the posted token must equal the CSRF cookie.
    private function require_csrf() {
        $field  = config_item('csrf_token_name');
        $cookie = (config_item('cookie_prefix') ?: '') . config_item('csrf_cookie_name');
        $posted = $_POST[$field] ?? null;
        $stored = $_COOKIE[$cookie] ?? null;
        if (!is_string($posted) || !is_string($stored) || $posted === '' || !hash_equals($stored, $posted)) {
            http_response_code(403);
            $this->call->view('pages/forbidden', ['title' => 'Request blocked']);
            exit;
        }
    }

    private function go($path) { redirect($path); exit; }

    private function not_found() {
        http_response_code(404);
        $this->call->view('pages/not_found', ['title' => 'Trip not found']);
        exit;
    }

    private function load_trip($id) {
        $id = (int) $id;
        $trip = $id > 0 ? $this->Trip_model->find_for_user($id, $this->uid()) : null;
        if (!$trip) $this->not_found();
        return $trip;
    }

    // READ: list (with optional search)
    public function index() {
        $q = trim((string) ($_GET['q'] ?? ''));
        $this->call->view('trips/index', [
            'title' => 'My Trips',
            'trips' => $this->Trip_model->all_for_user($this->uid(), $q),
            'q'     => $q,
            'flash' => $this->session->flashdata('success'),
            'flash_error' => $this->session->flashdata('error'),
        ]);
    }

    // READ: one trip + itinerary
    public function show($id) {
        $trip = $this->load_trip($id);
        $this->call->view('trips/show', [
            'title' => $trip['title'],
            'trip'  => $trip,
            'items' => $this->Trip_model->items($trip['id']),
            'flash' => $this->session->flashdata('success'),
            'flash_error' => $this->session->flashdata('error'),
        ]);
    }

    // CREATE: form
    public function create() {
        $this->call->view('trips/form', [
            'title'  => 'Plan a Trip',
            'mode'   => 'create',
            'action' => site_url('trips/store'),
            'trip'   => ['title'=>'','destination'=>'','start_date'=>'','end_date'=>'','budget'=>'','travelers'=>1,'status'=>'draft'],
            'errors' => [],
        ]);
    }

    public function generate_itinerary() {
        auth_require_login();
        integration_require_csrf();
        $rate_key = 'user:' . auth_user_id() . ':ip:' . integration_client_ip();
        $rate = integration_rate_limit_check($this->db, 'itinerary-generation', $rate_key, 10, 3600);
        if (!empty($rate['unavailable'])) integration_json_response(503, ['error' => 'Request throttling is temporarily unavailable.']);
        if (!$rate['allowed']) integration_json_response(429, ['error' => 'Too many itinerary requests. Please try again in ' . ceil($rate['retry_after'] / 60) . ' minutes.']);
        if (!integration_rate_limit_hit($this->db, 'itinerary-generation', $rate_key, 3600)) integration_json_response(503, ['error' => 'Request throttling is temporarily unavailable.']);
        $destination = trim((string) ($_POST['destination'] ?? ''));
        $start = $this->valid_date((string) ($_POST['start_date'] ?? ''));
        $end = $this->valid_date((string) ($_POST['end_date'] ?? ''));
        $budget = (float) ($_POST['budget'] ?? 0);
        $travelers = (int) ($_POST['travelers'] ?? 1);
        if ($destination === '' || !$start || !$end || $end < $start || $budget <= 0 || $travelers < 1) {
            integration_json_response(422, ['error' => 'Enter a destination, valid dates, a budget, and at least one traveler first.']);
        }
        $days = (int) $start->diff($end)->days + 1;
        if ($days > 21) integration_json_response(422, ['error' => 'AI itinerary generation supports trips up to 21 days.']);

        if (trim((string) config_item('gemini_api_key')) === '') {
            integration_json_response(503, ['error' => 'Gemini is not configured. Add GEMINI_API_KEY to the local environment.']);
        }
        $input = json_encode([
            'destination' => $destination,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'days' => $days,
            'budget_php' => $budget,
            'travelers' => $travelers,
            'preferences' => trim((string) ($_POST['preferences'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);
        $schema = [
            'type' => 'object',
            'properties' => ['items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'day' => ['type' => 'integer'],
                        'time' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['transport', 'stay', 'activity']],
                        'description' => ['type' => 'string'],
                        'estimated_cost' => ['type' => 'number'],
                        'estimated_co2_kg' => ['type' => 'number'],
                    ],
                    'required' => ['day', 'time', 'type', 'description', 'estimated_cost', 'estimated_co2_kg'],
                ],
            ]],
            'required' => ['items'],
        ];
        $response = integration_gemini_interact(
            $input,
            'Create a clear, practical low-carbon itinerary for the supplied destination and exact trip dates. Return scheduled items in chronological order with 24-hour HH:MM times. Include useful activities and travel transitions on every day, with realistic gaps and no overlapping plans; use no more than four items per day. Make the first and last days realistic for arrival and departure. Keep estimated costs in PHP and the total within the supplied budget. Never invent live availability, reservations, opening hours, or transport schedules. Keep descriptions concise and specific to the destination. Estimate emissions conservatively; use zero only for genuinely zero-emission activities.',
            ['max_output_tokens' => 4096, 'thinking_level' => 'low'],
            ['type' => 'text', 'mime_type' => 'application/json', 'schema' => $schema]
        );
        $generated = json_decode($response['text'], true);
        $items = $this->normalize_generated_items($generated['items'] ?? [], $days);
        if ($response['status'] < 200 || $response['status'] >= 300 || !$items) {
            $detail = trim((string) $response['error']);
            error_log(sprintf(
                'Gemini itinerary generation failed (HTTP %d): %s',
                $response['status'],
                $detail !== '' ? $detail : 'No valid itinerary items returned.'
            ));
            if ($response['status'] === 401 || $response['status'] === 403) {
                $message = 'Gemini rejected the API key or this project is not authorized to use the API. Check the key and API access.';
            } elseif ($response['status'] === 404) {
                $message = 'Gemini could not find any configured model or API endpoint. Check GEMINI_MODEL and GEMINI_FALLBACK_MODELS.';
            } elseif (!empty($response['temporary'])) {
                $message = 'Gemini is temporarily busy. EcoTrip retried the request and tried its backup model. Please try again shortly.';
            } elseif ($response['status'] === 429) {
                $message = 'Gemini rate limit or quota reached. Check the project quota and try again later.';
            } elseif ($response['status'] < 200 || $response['status'] >= 300) {
                $message = 'Gemini request failed with HTTP ' . $response['status'] . '.';
            } else {
                $message = 'Gemini responded, but did not return a valid itinerary.';
            }
            if ($detail !== '') $message .= ' Provider details: ' . mb_substr($detail, 0, 400);
            integration_json_response(502, ['error' => $message]);
        }
        integration_json_response(200, ['items' => $items, 'days' => $days]);
    }

    // CREATE: save
    public function store() {
        $this->require_csrf();
        $in = $this->input_data();
        $errors = $this->validate($in);
        if ($errors) {
            $this->call->view('trips/form', ['title'=>'Plan a Trip','mode'=>'create',
                'action'=>site_url('trips/store'),'trip'=>$in,'errors'=>$errors]);
            return;
        }
        $in['user_id'] = $this->uid();
        $id = $this->Trip_model->create_trip($in);
        $generated = json_decode((string) ($in['generated_itinerary'] ?? ''), true);
        $items = $this->normalize_generated_items($generated['items'] ?? [], $this->trip_days($in['start_date'], $in['end_date']));
        if ($items) $this->Trip_model->add_generated_items($id, $items);
        $this->session->set_flashdata('success', 'Trip created.');
        $this->go('trips/' . $id);
    }

    // UPDATE: form
    public function edit($id) {
        $trip = $this->load_trip($id);
        $this->call->view('trips/form', ['title'=>'Edit Trip','mode'=>'edit',
            'action'=>site_url('trips/'.$trip['id'].'/update'),'trip'=>$trip,'errors'=>[]]);
    }

    // UPDATE: save
    public function update($id) {
        $this->require_csrf();
        $trip = $this->load_trip($id);
        $in = $this->input_data();
        $errors = $this->validate($in);
        if ($errors) {
            $in['id'] = $trip['id'];
            $this->call->view('trips/form', ['title'=>'Edit Trip','mode'=>'edit',
                'action'=>site_url('trips/'.$trip['id'].'/update'),'trip'=>$in,'errors'=>$errors]);
            return;
        }
        $this->Trip_model->update_trip($trip['id'], $this->uid(), $in);
        $this->session->set_flashdata('success', 'Trip updated.');
        $this->go('trips/' . $trip['id']);
    }

    // DELETE (POST only; the form asks for confirmation in the browser too)
    public function delete($id) {
        $this->require_csrf();
        $trip = $this->load_trip($id);
        $this->Trip_model->delete_trip($trip['id'], $this->uid());
        $this->session->set_flashdata('success', 'Trip "' . $trip['title'] . '" deleted.');
        $this->go('trips');
    }

    private function input_data() {
        return [
            'title'       => trim((string) ($_POST['title'] ?? '')),
            'destination' => trim((string) ($_POST['destination'] ?? '')),
            'start_date'  => trim((string) ($_POST['start_date'] ?? '')),
            'end_date'    => trim((string) ($_POST['end_date'] ?? '')),
            'budget'      => trim((string) ($_POST['budget'] ?? '')),
            'travelers'   => trim((string) ($_POST['travelers'] ?? '')),
            'status'      => trim((string) ($_POST['status'] ?? '')) ?: 'draft',
            'generated_itinerary' => (string) ($_POST['generated_itinerary'] ?? ''),
        ];
    }

    private function trip_days($start, $end) {
        $start_date = $this->valid_date($start);
        $end_date = $this->valid_date($end);
        return $start_date && $end_date && $end_date >= $start_date ? min(21, (int) $start_date->diff($end_date)->days + 1) : 1;
    }

    private function normalize_generated_items($items, $days) {
        if (!is_array($items)) return [];
        $normalized = [];
        $day_counts = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $description = trim(strip_tags((string) ($item['description'] ?? '')));
            $time = (string) ($item['time'] ?? '09:00');
            if ($description === '' || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) continue;
            $type = (string) ($item['type'] ?? 'activity');
            if (!in_array($type, ['transport', 'stay', 'activity'], true)) $type = 'activity';
            $day = min(max(1, (int) ($item['day'] ?? 1)), max(1, $days));
            if (($day_counts[$day] ?? 0) >= 4) continue;
            $day_counts[$day] = ($day_counts[$day] ?? 0) + 1;
            $normalized[] = [
                'day' => $day,
                'time' => $time,
                'type' => $type,
                'description' => mb_substr($description, 0, 255),
                'estimated_cost' => max(0, (float) ($item['estimated_cost'] ?? 0)),
                'estimated_co2_kg' => max(0, (float) ($item['estimated_co2_kg'] ?? 0)),
            ];
        }
        usort($normalized, static function ($a, $b) {
            return ($a['day'] <=> $b['day']) ?: strcmp($a['time'], $b['time']);
        });
        return $normalized;
    }

    // Server-side validation: returns [field => message]
    private function validate($d) {
        $e = [];
        if ($d['title'] === '') $e['title'] = 'Trip title is required.';
        elseif (strlen($d['title']) > 150) $e['title'] = 'Title must be 150 characters or fewer.';

        if ($d['destination'] === '') $e['destination'] = 'Destination is required.';
        elseif (strlen($d['destination']) > 150) $e['destination'] = 'Destination must be 150 characters or fewer.';

        $s = $this->valid_date($d['start_date']);
        $en = $this->valid_date($d['end_date']);
        if ($d['start_date'] === '') $e['start_date'] = 'Start date is required.';
        elseif (!$s) $e['start_date'] = 'Start date is not a valid date.';
        if ($d['end_date'] === '') $e['end_date'] = 'End date is required.';
        elseif (!$en) $e['end_date'] = 'End date is not a valid date.';
        if ($s && $en && $en < $s) $e['end_date'] = 'End date cannot be before the start date.';

        if ($d['budget'] === '') $e['budget'] = 'Budget is required.';
        elseif (!is_numeric($d['budget']) || (float)$d['budget'] <= 0) $e['budget'] = 'Budget must be a number greater than 0.';
        elseif ((float)$d['budget'] > 99999999) $e['budget'] = 'Budget is too large.';

        if ($d['travelers'] === '') $e['travelers'] = 'Number of travelers is required.';
        elseif (!ctype_digit($d['travelers']) || (int)$d['travelers'] < 1 || (int)$d['travelers'] > 50)
            $e['travelers'] = 'Travelers must be a whole number from 1 to 50.';

        if (!in_array($d['status'], self::STATUSES, true)) $e['status'] = 'Invalid status.';
        return $e;
    }

    private function valid_date($v) {
        $dt = DateTime::createFromFormat('Y-m-d', $v);
        return ($dt && $dt->format('Y-m-d') === $v) ? $dt : null;
    }
}
