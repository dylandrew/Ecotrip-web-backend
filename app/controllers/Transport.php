<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Transport extends Controller {
    public function __construct() {
        parent::__construct();
        $this->call->model('Transport_model');
        $this->call->model('Trip_model');
    }

    public function index() {
        $user_id = auth_user_id();
        $trips = $user_id ? $this->Trip_model->all_for_user($user_id) : [];
        $requested_trip_id = (int) ($_GET['trip_id'] ?? 0);
        $selected_trip = null;
        foreach ($trips as $trip) {
            if ((int) $trip['id'] === $requested_trip_id) {
                $selected_trip = $trip;
                break;
            }
        }

        $options = $selected_trip
            ? $this->Transport_model->options_for_destination($selected_trip['destination'])
            : $this->Transport_model->all_options();
        $planned_travel = [];
        if ($selected_trip) {
            $planned_travel = array_values(array_filter(
                $this->Trip_model->items($selected_trip['id']),
                static function ($item) {
                    return ($item['item_type'] ?? '') === 'transport';
                }
            ));
        }
        $speeds = ['bus' => 42, 'train' => 65, 'bike' => 14, 'walk' => 4.5, 'ferry' => 32, 'carpool' => 45];
        foreach ($options as &$option) {
            $distance = max(0, (float) ($option['distance_km'] ?? 0));
            $minutes = (int) ceil($distance / ($speeds[$option['mode']] ?? 35) * 60);
            if (in_array($option['mode'], ['bus', 'train', 'ferry'], true)) $minutes += 20;
            $hours = intdiv($minutes, 60);
            $remaining = $minutes % 60;
            $option['estimated_duration'] = $hours ? $hours . 'h ' . $remaining . 'm' : $remaining . ' min';
        }
        unset($option);

        $user_address = '';
        if ($user_id) {
            $this->call->model('User_model');
            $user_record = $this->User_model->find_by_id($user_id);
            $user_address = trim((string) ($user_record['address'] ?? ''));
        }

        $this->call->view('transport/index', [
            'title' => 'Low-Carbon Routes',
            'options' => $options,
            'user_address' => $user_address,
            'trips' => $trips,
            'selected_trip' => $selected_trip,
            'planned_travel' => $planned_travel,
        ]);
    }
}
