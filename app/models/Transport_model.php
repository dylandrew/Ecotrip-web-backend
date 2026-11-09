<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Transport_model extends Model {
    protected $table = 'transport_options';
    public function all_options() {
        return $this->db->table($this->table)
            ->order_by('co2_kg_per_person')
            ->order_by('mode')
            ->get_all();
    }

    public function find_option($id) {
        return $this->db->table($this->table)
            ->where('id', $id)
            ->limit(1)
            ->get();
    }

    public function options_for_destination($destination) {
        $destination = trim((string) $destination);
        if ($destination === '') return [];

        return array_values(array_filter($this->all_options(), static function ($option) use ($destination) {
            foreach ([$option['origin'] ?? '', $option['destination'] ?? ''] as $location) {
                if (mb_stripos($location, $destination) !== false || mb_stripos($destination, $location) !== false) {
                    return true;
                }
            }
            return false;
        }));
    }

    public function save_option($id, array $option) {
        $fields = [
            'mode' => $option['mode'],
            'origin' => $option['origin'],
            'destination' => $option['destination'],
            'distance_km' => $option['distance_km'],
            'co2_kg_per_person' => $option['co2_kg_per_person'],
            'cost' => $option['cost'],
        ];
        if ($id) {
            return $this->db->table($this->table)->where('id', $id)->update($fields);
        }
        $this->db->table($this->table)->insert($fields);
        return $this->db->last_id();
    }

    public function delete_option($id) {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }
}
