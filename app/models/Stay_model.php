<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Stay_model extends Model {
    protected $table = 'stays';
    public function all_stays($location = '', $type = 'all') {
        $location = trim((string) $location);
        $query = $this->db->table($this->table);
        if ($type === 'green') {
            $query->where('eco_rating', '>', 0);
        } elseif ($type === 'standard') {
            $query->where('eco_rating', 0);
        }
        if ($location !== '') {
            $query->like('location', '%' . $location . '%');
        }
        return $query->order_by('eco_rating', 'DESC')->order_by('name')->get_all();
    }
    public function find_stay($id) {
        return $this->db->table($this->table)->where('id', $id)->get();
    }

    public function save_stay($id, array $stay) {
        $fields = [
            'name' => $stay['name'],
            'location' => $stay['location'],
            'eco_rating' => $stay['eco_rating'],
            'price_per_night' => $stay['price_per_night'],
            'certification' => $stay['certification'],
            'description' => $stay['description'],
        ];
        if ($id) {
            return $this->db->table($this->table)->where('id', $id)->update($fields);
        }
        $this->db->table($this->table)->insert($fields);
        return $this->db->last_id();
    }

    public function delete_stay($id) {
        return $this->db->table($this->table)->where('id', $id)->delete();
    }
}
