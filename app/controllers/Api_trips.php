<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_trips extends Api_v1 {
    public function trips_index() {
        $userId = $this->uid();
        $query = (string) $this->request->get('q', '');
        api_json_response(200, [
            'trips' => $this->Trip_model->all_for_user($userId, $query),
        ]);
    }

    public function trips_create() {
        $uid = $this->uid();
        $body = $this->body();
        $trip = $this->validated_trip($body);
        if (isset($trip['error'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', $trip['error']);
        }
        $items = $this->submitted_itinerary_items($body, $trip['start_date'], $trip['end_date']);
        $trip['user_id'] = $uid;

        $this->db->transaction();
        try {
            $id = $this->Trip_model->create_trip($trip);
            if ($items) {
                $this->Trip_model->add_generated_items($id, $items);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->roll_back();
            throw $error;
        }

        api_json_response(201, [
            'trip' => $this->Trip_model->find_for_user($id, $uid),
            'items' => $this->Trip_model->items($id),
        ]);
    }
    public function trips_show($id) {
        $userId = $this->uid();
        $tripId = (int) $id;
        $trip = $this->Trip_model->find_for_user($tripId, $userId);
        if (!$trip) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }

        api_json_response(200, [
            'trip' => $trip,
            'items' => $this->Trip_model->items($tripId),
        ]);
    }

    public function trips_update($id) {
        $uid = $this->uid();
        $tripId = (int) $id;
        if (!$this->Trip_model->find_for_user($tripId, $uid)) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }
        $body = $this->body();
        $trip = $this->validated_trip($body);
        if (isset($trip['error'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', $trip['error']);
        }
        $replaceItems = array_key_exists('itinerary_items', $body);
        $items = $replaceItems
            ? $this->submitted_itinerary_items($body, $trip['start_date'], $trip['end_date'])
            : [];

        $this->db->transaction();
        try {
            $this->Trip_model->update_trip($tripId, $uid, $trip);
            if ($replaceItems) {
                $this->Trip_model->replace_generated_items($tripId, $items);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->roll_back();
            throw $error;
        }

        api_json_response(200, [
            'trip' => $this->Trip_model->find_for_user($tripId, $uid),
            'items' => $this->Trip_model->items($tripId),
        ]);
    }

    public function itinerary_item_update($id, $itemId) {
        $uid = $this->uid();
        $tripId = (int) $id;
        $itemId = (int) $itemId;
        $trip = $this->Trip_model->find_for_user($tripId, $uid);
        if (!$trip) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }

        $body = $this->body();
        $item = $this->validated_itinerary_item($body, $trip);
        if (isset($item['error'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', $item['error']);
        }
        if (!$this->Trip_model->update_item($tripId, $itemId, $item)) {
            api_json_response(404, null, 'NOT_FOUND', 'Itinerary stop not found.');
        }

        api_json_response(200, ['item' => $this->Trip_model->find_item($tripId, $itemId)]);
    }

    public function trip_experience_update($id) {
        $uid = $this->uid();
        $tripId = (int) $id;
        $trip = $this->Trip_model->find_for_user($tripId, $uid);
        if (!$trip) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }
        if ($trip['status'] !== 'completed') {
            api_json_response(409, null, 'TRIP_NOT_COMPLETED', 'Mark the trip as completed before sharing it as a reference.');
        }

        $body = $this->body();
        $share = filter_var($body['share_experience'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $notes = is_string($body['experience_notes'] ?? null)
            ? trim(strip_tags($body['experience_notes']))
            : '';
        $rating = filter_var($body['experience_rating'] ?? null, FILTER_VALIDATE_INT);
        if ($share === null || mb_strlen($notes) > 2000
            || ($share && ($rating === false || $rating < 1 || $rating > 5))) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Choose a sharing option, rate the trip from 1 to 5 stars, and keep notes within 2,000 characters.');
        }
        if ($share && $notes === '') {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Add a few experience notes before sharing this trip.');
        }
        if (!$this->Trip_model->update_trip_experience($tripId, $uid, $share, $notes, $rating)) {
            api_json_response(500, null, 'UPDATE_FAILED', 'The trip experience could not be saved.');
        }

        api_json_response(200, ['trip' => $this->Trip_model->find_for_user($tripId, $uid)]);
    }

    public function shared_experiences() {
        $this->uid();
        $location = trim((string) $this->request->get('location', ''));
        if (mb_strlen($location) > 100) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Location search must be 100 characters or fewer.');
        }
        api_json_response(200, [
            'experiences' => $this->Trip_model->shared_completed_items($location),
        ]);
    }

    public function copy_shared_experience($id) {
        $userId = $this->uid();
        $source = $this->Trip_model->find_shared_trip_for_copy((int) $id);
        if (!$source) {
            api_json_response(404, null, 'NOT_FOUND', 'This shared trip reference is no longer available.');
        }

        $body = $this->body();
        $title = is_string($body['title'] ?? null)
            ? trim(strip_tags($body['title']))
            : 'Copy of ' . $source['title'];
        $trip = $this->validated_trip([
            'title' => $title,
            'destination' => $source['destination'],
            'start_date' => $body['start_date'] ?? '',
            'end_date' => $body['end_date'] ?? '',
            'budget' => $body['budget'] ?? null,
            'travelers' => $body['travelers'] ?? 1,
            'status' => 'draft',
        ]);
        if (isset($trip['error'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', $trip['error']);
        }

        $start = new DateTimeImmutable($trip['start_date']);
        $end = new DateTimeImmutable($trip['end_date']);
        $duration = (int) $start->diff($end)->days + 1;
        $sourceItems = $this->Trip_model->shared_trip_items_for_copy((int) $source['id']);
        $items = [];
        foreach ($sourceItems as $item) {
            $day = (int) $item['day_number'];
            $time = substr((string) $item['start_time'], 0, 5);
            if ($day < 1 || $day > $duration || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                api_json_response(422, null, 'INVALID_REFERENCE', 'Choose dates that cover all itinerary days in this reference.');
            }
            $items[] = [
                'day' => $day,
                'type' => $item['item_type'],
                'description' => $item['description'],
                'time' => $time,
                'estimated_cost' => (float) $item['est_cost'],
                'estimated_co2_kg' => (float) $item['est_co2_kg'],
            ];
        }

        $trip['user_id'] = $userId;
        $this->db->transaction();
        try {
            $tripId = $this->Trip_model->create_trip($trip);
            if ($items) {
                $this->Trip_model->add_generated_items($tripId, $items);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->roll_back();
            throw $error;
        }

        api_audit_log($this->db, $userId, 'experience.copied', 'trip', $tripId);
        api_json_response(201, [
            'trip' => $this->Trip_model->find_for_user($tripId, $userId),
            'items' => $this->Trip_model->items($tripId),
        ]);
    }

    private function validated_itinerary_item(array $body, array $trip) {
        $description = is_string($body['description'] ?? null)
            ? trim(strip_tags($body['description']))
            : '';
        $time = (string) ($body['time'] ?? '');
        $type = (string) ($body['type'] ?? '');
        $day = filter_var($body['day'] ?? null, FILTER_VALIDATE_INT);
        $cost = filter_var($body['estimated_cost'] ?? null, FILTER_VALIDATE_FLOAT);
        $co2 = filter_var($body['estimated_co2_kg'] ?? null, FILTER_VALIDATE_FLOAT);
        $completed = filter_var($body['completed'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $share = filter_var($body['share_experience'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $notes = is_string($body['experience_notes'] ?? null)
            ? trim(strip_tags($body['experience_notes']))
            : '';
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $trip['start_date']);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $trip['end_date']);
        $days = $start && $end ? (int) $start->diff($end)->days + 1 : 0;

        if ($description === '' || mb_strlen($description) > 255) {
            return ['error' => 'Enter a stop description of 1 to 255 characters.'];
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return ['error' => 'Enter a valid stop time in 24-hour format.'];
        }
        if ($day === false || $day < 1 || $day > $days) {
            return ['error' => 'Choose a trip day within the trip dates.'];
        }
        if (!in_array($type, ['transport', 'stay', 'activity'], true)) {
            return ['error' => 'Choose a valid itinerary stop type.'];
        }
        if ($cost === false || $cost < 0 || $cost > 99999999.99
            || $co2 === false || $co2 < 0 || $co2 > 9999.99) {
            return ['error' => 'Enter valid non-negative cost and emissions estimates.'];
        }
        if ($completed === null || $share === null || mb_strlen($notes) > 2000) {
            return ['error' => 'Enter valid completion and sharing settings and keep notes within 2,000 characters.'];
        }
        if ($share && (!$completed || $notes === '')) {
            return ['error' => 'Complete the stop and add experience notes before sharing it publicly.'];
        }

        return [
            'day_number' => $day,
            'item_type' => $type,
            'description' => $description,
            'start_time' => $time,
            'est_cost' => $cost,
            'est_co2_kg' => $co2,
            'completed' => (int) $completed,
            'experience_notes' => $notes !== '' ? $notes : null,
            'share_experience' => (int) $share,
        ];
    }

    private function submitted_itinerary_items(array $body, $startDate, $endDate) {
        if (!array_key_exists('itinerary_items', $body)) {
            return [];
        }
        if (!is_array($body['itinerary_items'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'The generated itinerary must be a list of itinerary items.');
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        $days = $start && $end && $end >= $start ? (int) $start->diff($end)->days + 1 : 0;
        $items = $this->normalize_generated_items($body['itinerary_items'], $days);
        if (count($items) !== count($body['itinerary_items'])) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'One or more generated itinerary items are invalid. Generate the itinerary again and retry.');
        }
        return $items;
    }
    public function trips_delete($id) {
        $userId = $this->uid();
        $tripId = (int) $id;
        if (!$this->Trip_model->find_for_user($tripId, $userId)) {
            api_json_response(404, null, 'NOT_FOUND', 'Trip not found.');
        }

        $this->Trip_model->delete_trip($tripId, $userId);
        api_audit_log($this->db, $userId, 'trip.deleted', 'trip', $tripId);
        api_json_response(200, ['deleted' => true]);
    }

    private function validated_trip($body) {
        $text = static function ($value) {
            return is_string($value) ? trim(strip_tags($value)) : '';
        };
        $title = $text($body['title'] ?? '');
        $destination = $text($body['destination'] ?? '');
        $startDate = $text($body['start_date'] ?? '');
        $endDate = $text($body['end_date'] ?? '');
        $budget = filter_var($body['budget'] ?? null, FILTER_VALIDATE_FLOAT);
        $travelers = filter_var($body['travelers'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($body['status'] ?? 'draft');

        if ($title === '' || mb_strlen($title) > 150) {
            return ['error' => 'Enter a trip title of 1 to 150 characters.'];
        }
        if ($destination === '' || mb_strlen($destination) > 150) {
            return ['error' => 'Enter a destination of 1 to 150 characters.'];
        }

        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        if (
            !$start
            || !$end
            || $start->format('Y-m-d') !== $startDate
            || $end->format('Y-m-d') !== $endDate
            || $endDate < $startDate
        ) {
            return ['error' => 'Enter valid trip dates and ensure the end date is not before the start date.'];
        }
        if ($budget === false || $budget <= 0 || $budget > 99999999.99) {
            return ['error' => 'Enter a valid budget.'];
        }
        if ($travelers === false || $travelers < 1 || $travelers > 50) {
            return ['error' => 'Travelers must be from 1 to 50.'];
        }
        if (!in_array($status, ['draft', 'planned', 'booked', 'completed'], true)) {
            return ['error' => 'Choose a valid trip status.'];
        }

        return [
            'title' => $title,
            'destination' => $destination,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'budget' => $budget,
            'travelers' => $travelers,
            'status' => $status,
        ];
    }
    public function generate_itinerary() {
        $uid = $this->uid();
        $rateKey = 'user:' . $uid . ':ip:' . integration_client_ip();
        $this->consume_rate_limit('itinerary-generation', $rateKey, 10, 3600, 'Too many itinerary requests. Try again later.');

        $body = $this->body();
        $destination = trim((string) ($body['destination'] ?? ''));
        $startingCity = trim((string) ($body['starting_city'] ?? ''));
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($body['start_date'] ?? ''));
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($body['end_date'] ?? ''));
        $budget = (float) ($body['budget'] ?? 0);
        $travelers = (int) ($body['travelers'] ?? 1);
        if (mb_strlen($startingCity) > 100) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Starting city or municipality must be 100 characters or fewer.');
        }
        if (
            $destination === ''
            || !$start
            || !$end
            || $end < $start
            || $budget <= 0
            || $travelers < 1
        ) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'Enter a destination, valid dates, a budget, and at least one traveler.');
        }

        $days = (int) $start->diff($end)->days + 1;
        if ($days > 21) {
            api_json_response(422, null, 'VALIDATION_ERROR', 'AI itinerary generation supports trips up to 21 days.');
        }
        if (trim((string) config_item('gemini_api_key')) === '') {
            api_json_response(503, null, 'GEMINI_NOT_CONFIGURED', 'Gemini is not configured.');
        }

        $input = json_encode([
            'destination' => $destination,
            'starting_city' => $startingCity,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'days' => $days,
            'budget_php' => $budget,
            'travelers' => $travelers,
            'preferences' => trim((string) ($body['preferences'] ?? '')),
        ], JSON_UNESCAPED_UNICODE);
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => [
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
                ],
            ],
            'required' => ['items'],
        ];
        $response = integration_gemini_interact(
            $input,
            'Create a clear, practical low-carbon itinerary for the supplied destination and exact trip dates. Return scheduled items in chronological order with 24-hour HH:MM times. Include useful activities and travel transitions on every day, with realistic gaps and no overlapping plans; use no more than four items per day. When starting_city is supplied, make the first item on day 1 an outbound transport leg from that city or municipality to the destination, and include its cost in the budget. Make the first and last days realistic for arrival and departure. Keep estimated costs in PHP and the total within the supplied budget. Never invent live availability, reservations, opening hours, or transport schedules. Keep descriptions concise and specific to the destination. Estimate emissions conservatively; use zero only for genuinely zero-emission activities.',
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
                $message = 'Gemini could not find a configured model or API endpoint. Check GEMINI_MODEL and GEMINI_FALLBACK_MODELS.';
            } elseif ($response['status'] === 429) {
                $message = 'Gemini rate limit or quota reached. Check the project quota and try again later.';
            } elseif (!empty($response['temporary'])) {
                $message = 'Gemini is temporarily unavailable. EcoTrip retried the request and its backup model; please try again shortly.';
            } elseif ($response['status'] < 200 || $response['status'] >= 300) {
                $message = 'Gemini request failed with HTTP ' . $response['status'] . '.';
            } else {
                $message = 'Gemini responded, but did not return a valid itinerary. Please try again.';
            }
            if ($detail !== '') {
                $message .= ' Provider details: ' . mb_substr($detail, 0, 400);
            }
            api_json_response(502, null, 'GEMINI_ERROR', $message);
        }

        api_json_response(200, ['items' => $items, 'days' => $days]);
    }
    private function normalize_generated_items($items, $days) {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        $dayCounts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $description = trim(strip_tags((string) ($item['description'] ?? '')));
            $time = (string) ($item['time'] ?? '09:00');
            if ($description === '' || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                continue;
            }

            $day = min(max(1, (int) ($item['day'] ?? 1)), max(1, $days));
            if (($dayCounts[$day] ?? 0) >= 4) {
                continue;
            }
            $dayCounts[$day] = ($dayCounts[$day] ?? 0) + 1;
            $type = (string) ($item['type'] ?? 'activity');
            $normalized[] = [
                'day' => $day,
                'time' => $time,
                'type' => in_array($type, ['transport', 'stay', 'activity'], true) ? $type : 'activity',
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
}
