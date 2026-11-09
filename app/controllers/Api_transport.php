<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Api_transport extends Api_v1 {
    public function transport_index(){
        $uid = $this->uid(false);
        $trips = $uid ? $this->Trip_model->all_for_user($uid) : [];
        $tripId = (int) $this->request->get('trip_id', 0);
        $selected = null;
        foreach ($trips as $trip) {
            if ((int) $trip['id'] === $tripId) {
                $selected = $trip;
                break;
            }
        }

        $options = $selected
            ? $this->Transport_model->options_for_destination($selected['destination'])
            : $this->Transport_model->all_options();
        $speeds = ['bus' => 42, 'train' => 65, 'bike' => 14, 'walk' => 4.5, 'ferry' => 32, 'carpool' => 45];
        foreach ($options as &$option) {
            $distance = max(0, (float) ($option['distance_km'] ?? 0));
            $minutes = (int) ceil($distance / ($speeds[$option['mode']] ?? 35) * 60);
            if (in_array($option['mode'], ['bus', 'train', 'ferry'], true)) {
                $minutes += 20;
            }
            $hours = intdiv($minutes, 60);
            $remaining = $minutes % 60;
            $option['estimated_duration'] = $hours ? $hours . 'h ' . $remaining . 'm' : $remaining . ' min';
        }
        unset($option);

        $planned = $selected
            ? array_values(array_filter(
                $this->Trip_model->items($selected['id']),
                static function ($item) {
                    return ($item['item_type'] ?? '') === 'transport';
                }
            ))
            : [];
        $userAddress = '';
        if ($uid) {
            $record = $this->User_model->find_by_id($uid);
            $userAddress = (string) ($record['address'] ?? '');
        }

        api_json_response(200, [
            'options' => $options,
            'trips' => $trips,
            'selected_trip' => $selected,
            'planned_travel' => $planned,
            'user_address' => $userAddress,
        ]);
    }
}
