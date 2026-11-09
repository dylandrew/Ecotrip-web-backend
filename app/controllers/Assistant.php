<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class Assistant extends Controller {
    public function index() {
        $this->call->view('pages/assistant', [
            'title' => 'Trip Assistant',
            'gemini_ready' => trim((string) config_item('gemini_api_key')) !== '',
        ]);
    }

    public function message() {
        auth_require_login();
        integration_require_csrf();
        $rate_key = 'user:' . auth_user_id() . ':ip:' . integration_client_ip();
        $rate = integration_rate_limit_check($this->db, 'assistant-message', $rate_key, 20, 3600);
        if (!empty($rate['unavailable'])) integration_json_response(503, ['error' => 'Request throttling is temporarily unavailable.']);
        if (!$rate['allowed']) integration_json_response(429, ['error' => 'Too many assistant requests. Please try again in ' . ceil($rate['retry_after'] / 60) . ' minutes.']);
        if (!integration_rate_limit_hit($this->db, 'assistant-message', $rate_key, 3600)) integration_json_response(503, ['error' => 'Request throttling is temporarily unavailable.']);
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message === '' || mb_strlen($message) > 1500) {
            integration_json_response(422, ['error' => 'Enter a message of 1,500 characters or fewer.']);
        }
        if (trim((string) config_item('gemini_api_key')) === '') {
            integration_json_response(503, ['error' => 'Gemini is not configured. Add GEMINI_API_KEY to the local environment.']);
        }

        $history = json_decode((string) ($_POST['history'] ?? '[]'), true);
        $conversation = [];
        foreach (array_slice(is_array($history) ? $history : [], -6) as $turn) {
            if (!is_array($turn) || !in_array($turn['role'] ?? '', ['user', 'model'], true)) continue;
            $text = trim(strip_tags((string) ($turn['text'] ?? '')));
            if ($text === '') continue;
            $speaker = $turn['role'] === 'user' ? 'Traveler' : 'EcoTrip';
            $conversation[] = $speaker . ': ' . mb_substr($text, 0, 1000);
        }
        $conversation[] = 'Traveler: ' . $message;

        $response = integration_gemini_interact(
            implode("\n\n", $conversation),
            'You are EcoTrip, a concise and friendly sustainable-travel assistant. Help users plan practical, lower-carbon trips, compare transport modes, estimate budgets, and choose eco-conscious stays. The app has no live transit feed: never invent or assert specific operators, service availability, departure schedules, fares, weather, or booking status. Recommend general transport options unless verified details are supplied. Do not claim one option is the lowest-carbon without comparison data. Label route-time and emissions estimates clearly, and ask a brief clarifying question when key trip details are missing.',
            ['max_output_tokens' => 600, 'thinking_level' => 'low']
        );
        if ($response['status'] < 200 || $response['status'] >= 300 || trim($response['text']) === '') {
            $detail = preg_replace('/\s+/', ' ', trim((string) ($response['error'] ?? '')));
            error_log(sprintf(
                'Gemini assistant request failed (HTTP %d, model %s, temporary %s): %s',
                (int) ($response['status'] ?? 0),
                (string) ($response['model'] ?? 'unknown'),
                !empty($response['temporary']) ? 'yes' : 'no',
                $detail !== '' ? mb_substr($detail, 0, 300) : 'No response text returned.'
            ));
            if (!empty($response['temporary'])) {
                $error = 'Gemini is temporarily busy. EcoTrip retried the request and tried its backup model. Please try again shortly.';
            } elseif ($response['status'] === 429) {
                $error = 'Gemini is busy right now. Wait a moment and try again.';
            } else {
                $error = 'The EcoTrip assistant could not respond. Check Gemini API access and try again.';
            }
            integration_json_response(502, ['error' => $error]);
        }
        integration_json_response(200, ['reply' => $response['text']]);
    }
}