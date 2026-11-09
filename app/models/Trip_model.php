<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Trip_model extends Model {
    protected $table = 'trips';

    // READ: all trips of a user, optional search on title/destination, with item counts
    public function all_for_user($user_id, $search = '') {
        $query = $this->db->table('trips')
            ->where('user_id', $user_id);
        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->grouped(static function ($query) use ($term) {
                $query->like('title', $term)
                    ->or_like('destination', $term);
            });
        }
        $trips = $query->order_by('start_date', 'DESC')->get_all();
        foreach ($trips as &$trip) {
            $trip['item_count'] = $this->db->table('itinerary_items')
                ->where('trip_id', $trip['id'])
                ->count();
        }
        unset($trip);

        return $trips;
    }

    public function all_for_admin() {
        return $this->db->table('trips t')
            ->select('t.id, t.user_id, t.title, t.destination, t.start_date, t.end_date, t.budget, t.travelers, t.total_co2_kg, t.status, t.created_at, u.name AS traveler_name, u.email AS traveler_email')
            ->join('users u', 'u.id = t.user_id')
            ->order_by('t.start_date', 'DESC')
            ->get_all();
    }

    public function find_for_admin($id) {
        return $this->db->table('trips')
            ->where('id', $id)
            ->limit(1)
            ->get();
    }

    // READ one (scoped to owner so users cannot open other people's trips)
    public function find_for_user($id, $user_id) {
        return $this->db->table('trips')
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->get();
    }

    public function items($trip_id) {
        return $this->db->table('itinerary_items')
            ->where('trip_id', $trip_id)
            ->order_by('day_number')
            ->order_by('start_time')
            ->get_all();
    }

    public function find_item($trip_id, $item_id) {
        return $this->db->table('itinerary_items')
            ->where('trip_id', $trip_id)
            ->where('id', $item_id)
            ->limit(1)
            ->get();
    }

    public function update_item($trip_id, $item_id, $item) {
        if (!$this->find_item($trip_id, $item_id)) return false;
        return $this->db->table('itinerary_items')
            ->where('trip_id', $trip_id)
            ->where('id', $item_id)
            ->update($item) !== false;
    }

    public function shared_completed_items($location = '') {
        $query = $this->db->table('itinerary_items i')
            ->select('i.id, i.day_number, i.item_type, i.description, i.start_time, i.experience_notes, t.destination AS location')
            ->join('trips t', 't.id = i.trip_id')
            ->where('i.completed', 1)
            ->where('i.share_experience', 1);
        if ($location !== '') {
            $term = '%' . $location . '%';
            $query->grouped(static function ($query) use ($term) {
                $query->like('t.destination', $term)
                    ->or_like('i.description', $term);
            });
        }
        $items = $query->order_by('t.end_date', 'DESC')
            ->order_by('i.start_time')
            ->limit(50)
            ->get_all();
        foreach ($items as &$item) {
            $item['reference_type'] = 'stop';
        }
        unset($item);

        $trips = $this->db->table('trips')
            ->select('id, title, destination, experience_notes, experience_rating, start_date, end_date')
            ->where('status', 'completed')
            ->where('share_experience', 1);
        if ($location !== '') {
            $term = '%' . $location . '%';
            $trips->grouped(static function ($trips) use ($term) {
                $trips->like('destination', $term)
                    ->or_like('title', $term);
            });
        }
        $trip_references = $trips->order_by('end_date', 'DESC')
            ->limit(50)
            ->get_all();
        $trip_ids = array_map(static fn($trip) => (int) $trip['id'], $trip_references);
        $places_by_trip = [];
        if ($trip_ids) {
            $place_query = $this->db->table('itinerary_items')
                ->select('trip_id, day_number, item_type, description, start_time');
            $place_query->grouped(static function ($place_query) use ($trip_ids) {
                foreach ($trip_ids as $index => $trip_id) {
                    if ($index === 0) {
                        $place_query->where('trip_id', $trip_id);
                    } else {
                        $place_query->or_where('trip_id', $trip_id);
                    }
                }
            });
            foreach ($place_query->order_by('day_number')->order_by('start_time')->get_all() as $place) {
                $places_by_trip[(int) $place['trip_id']][] = [
                    'day_number' => (int) $place['day_number'],
                    'item_type' => $place['item_type'],
                    'description' => $place['description'],
                    'start_time' => $place['start_time'],
                ];
            }
        }
        foreach ($trip_references as &$trip) {
            $trip['reference_type'] = 'trip';
            $trip['day_number'] = 0;
            $trip['item_type'] = 'trip';
            $trip['description'] = $trip['title'];
            $trip['start_time'] = null;
            $trip['location'] = $trip['destination'];
            $trip['places'] = $places_by_trip[(int) $trip['id']] ?? [];
        }
        unset($trip);

        return array_slice(array_merge($trip_references, $items), 0, 50);
    }

    public function find_shared_trip_for_copy($id) {
        return $this->db->table('trips')
            ->where('id', $id)
            ->where('status', 'completed')
            ->where('share_experience', 1)
            ->limit(1)
            ->get();
    }

    public function shared_trip_items_for_copy($trip_id) {
        return $this->db->table('itinerary_items')
            ->select('day_number, item_type, description, start_time, est_cost, est_co2_kg')
            ->where('trip_id', $trip_id)
            ->order_by('day_number')
            ->order_by('start_time')
            ->get_all();
    }

    public function all_experiences_for_admin() {
        $trips = $this->db->table('trips t')
            ->select('t.id, t.title, t.destination, t.start_date, t.end_date, t.experience_notes, t.experience_rating, t.share_experience, t.user_id, u.name AS traveler_name, u.email AS traveler_email')
            ->join('users u', 'u.id = t.user_id')
            ->where('t.status', 'completed')
            ->order_by('t.end_date', 'DESC')
            ->get_all();
        foreach ($trips as &$trip) {
            $trip['record_type'] = 'trip';
            $trip['reference_title'] = $trip['title'];
        }
        unset($trip);

        $stops = $this->db->table('itinerary_items i')
            ->select('i.id, i.trip_id, i.day_number, i.item_type, i.description, i.experience_notes, i.share_experience, i.completed, t.title AS trip_title, t.destination, t.start_date, t.end_date, t.user_id, u.name AS traveler_name, u.email AS traveler_email')
            ->join('trips t', 't.id = i.trip_id')
            ->join('users u', 'u.id = t.user_id')
            ->where('i.completed', 1)
            ->order_by('t.end_date', 'DESC')
            ->order_by('i.day_number')
            ->order_by('i.start_time')
            ->get_all();
        foreach ($stops as &$stop) {
            $stop['record_type'] = 'stop';
            $stop['reference_title'] = $stop['description'];
            $stop['experience_rating'] = null;
        }
        unset($stop);

        return array_merge($trips, $stops);
    }

    public function update_trip_experience_for_admin($id, $share, $notes, $rating) {
        return $this->db->table('trips')
            ->where('id', $id)
            ->where('status', 'completed')
            ->update([
                'share_experience' => (int) $share,
                'experience_notes' => $notes !== '' ? $notes : null,
                'experience_rating' => $rating,
            ]) !== false;
    }

    public function find_item_for_admin($id) {
        return $this->db->table('itinerary_items')
            ->where('id', $id)
            ->where('completed', 1)
            ->limit(1)
            ->get();
    }

    public function update_item_experience_for_admin($id, $share, $notes) {
        return $this->db->table('itinerary_items')
            ->where('id', $id)
            ->where('completed', 1)
            ->update([
                'share_experience' => (int) $share,
                'experience_notes' => $notes !== '' ? $notes : null,
            ]) !== false;
    }

    public function delete_trip_experience_for_admin($id) {
        return $this->db->table('trips')
            ->where('id', $id)
            ->where('status', 'completed')
            ->update([
                'share_experience' => 0,
                'experience_notes' => null,
                'experience_rating' => null,
            ]) !== false;
    }

    public function delete_item_experience_for_admin($id) {
        return $this->db->table('itinerary_items')
            ->where('id', $id)
            ->where('completed', 1)
            ->update([
                'share_experience' => 0,
                'experience_notes' => null,
            ]) !== false;
    }

    public function items_for_day($trip_id, $day_number) {
        return $this->db->table('itinerary_items')
            ->where('trip_id', $trip_id)
            ->where('day_number', $day_number)
            ->order_by('start_time')
            ->get_all();
    }

    public function add_generated_items($trip_id, $items) {
        foreach ($items as $item) {
            $co2 = max(0, (float) $item['estimated_co2_kg']);
            $this->db->table('itinerary_items')->insert([
                'trip_id' => $trip_id,
                'day_number' => $item['day'],
                'item_type' => $item['type'],
                'description' => $item['description'],
                'start_time' => $item['time'],
                'est_cost' => max(0, (float) $item['estimated_cost']),
                'est_co2_kg' => $co2,
            ]);
        }
    }

    public function replace_generated_items($trip_id, $items) {
        $this->db->table('itinerary_items')->where('trip_id', $trip_id)->delete();
        $this->add_generated_items($trip_id, $items);
    }

    // CREATE
    public function create_trip($d) {
        $this->db->table('trips')->insert([
            'user_id' => $d['user_id'],
            'title' => $d['title'],
            'destination' => $d['destination'],
            'start_date' => $d['start_date'],
            'end_date' => $d['end_date'],
            'budget' => $d['budget'],
            'travelers' => $d['travelers'],
            'status' => $d['status'],
        ]);
        return $this->db->last_id();
    }

    // UPDATE (WHERE id AND user_id so only the intended record changes)
    public function update_trip($id, $user_id, $d) {
        return $this->db->table('trips')
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->update([
                'title' => $d['title'],
                'destination' => $d['destination'],
                'start_date' => $d['start_date'],
                'end_date' => $d['end_date'],
                'budget' => $d['budget'],
                'travelers' => $d['travelers'],
                'status' => $d['status'],
            ]);
    }

    public function update_trip_experience($id, $user_id, $share, $notes, $rating = null) {
        $query = $this->db->table('trips')
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->where('status', 'completed');
        $fields = ['share_experience' => (int) $share];
        if ($share) {
            $fields['experience_notes'] = $notes;
            $fields['experience_rating'] = (int) $rating;
        }
        return $query->update($fields) !== false;
    }

    // DELETE (itinerary items, bookings, etc. go with it via ON DELETE CASCADE)
    public function delete_trip($id, $user_id) {
        return $this->db->table('trips')
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->delete();
    }

    public function delete_for_admin($id) {
        return $this->db->table('trips')->where('id', $id)->delete();
    }

    public function set_status_for_admin($id, $status) {
        if (!in_array($status, ['draft', 'planned', 'booked', 'completed'], true)) return false;
        return $this->db->table('trips')->where('id', $id)->update(['status' => $status]);
    }

    public function stats($user_id) {
        $stats = $this->db->table('trips')
            ->select_count('id', 'trips')
            ->select_sum('budget', 'budget')
            ->where('user_id', $user_id)
            ->get();
        $stats['budget'] = $stats['budget'] ?? 0;
        return $stats;
    }

    public function upcoming($user_id, $limit = 5) {
        return $this->db->table('trips')
            ->where('user_id', $user_id)
            ->where('end_date', '>=', date('Y-m-d'))
            ->order_by('start_date')
            ->limit(max(0, (int) $limit))
            ->get_all();
    }
}
