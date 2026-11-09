<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Booking_model extends Model {
    protected $table = 'bookings';

    public function all_for_user($user_id) {
        $bookings = $this->db->table('bookings b')
            ->select('b.id, b.trip_id, b.stay_id, b.reservation_name, b.payment_category, b.amount, b.offset_amount, b.paymongo_ref, b.payment_status, b.created_at, t.title AS trip_title, s.name AS stay_name')
            ->join('trips t', 't.id = b.trip_id')
            ->left_join('stays s', 's.id = b.stay_id')
            ->where('t.user_id', $user_id)
            ->order_by('b.created_at', 'DESC')
            ->get_all();
        foreach ($bookings as &$booking) {
            $booking['stay_name'] = $booking['stay_name'] ?? $booking['reservation_name'];
        }
        unset($booking);
        return $bookings;
    }

    public function all_for_admin() {
        $bookings = $this->db->table('bookings b')
            ->select('b.id, b.trip_id, b.stay_id, b.reservation_name, b.payment_category, b.amount, b.offset_amount, b.paymongo_ref, b.payment_status, b.created_at, t.title AS trip_title, t.destination, u.name AS traveler_name, u.email AS traveler_email, s.name AS stay_name')
            ->join('trips t', 't.id = b.trip_id')
            ->join('users u', 'u.id = t.user_id')
            ->left_join('stays s', 's.id = b.stay_id')
            ->order_by('b.created_at', 'DESC')
            ->get_all();
        foreach ($bookings as &$booking) {
            $booking['stay_name'] = $booking['stay_name'] ?? $booking['reservation_name'];
        }
        unset($booking);
        return $bookings;
    }

    public function find_for_user($id, $user_id) {
        $booking = $this->db->table('bookings b')
            ->select('b.id, b.trip_id, b.stay_id, b.reservation_name, b.payment_category, b.amount, b.offset_amount, b.paymongo_ref, b.payment_status, b.created_at, t.title AS trip_title, t.destination, s.name AS stay_name')
            ->join('trips t', 't.id = b.trip_id')
            ->left_join('stays s', 's.id = b.stay_id')
            ->where('b.id', $id)
            ->where('t.user_id', $user_id)
            ->get();
        if (!$booking) return null;
        $booking['stay_name'] = $booking['stay_name'] ?? $booking['reservation_name'];
        return $booking;
    }

    public function find_by_reference($reference) {
        return $this->db->table($this->table)
            ->select('id, amount, payment_status, paymongo_ref')
            ->where('paymongo_ref', $reference)
            ->limit(1)
            ->get();
    }

    public function find_for_admin($id) {
        return $this->db->table($this->table)
            ->select('id, amount, payment_status, paymongo_ref')
            ->where('id', $id)
            ->limit(1)
            ->get();
    }

    public function webhook_event_exists($event_id) {
        return (bool) $this->db->table('paymongo_webhook_events')
            ->where('event_id', $event_id)
            ->limit(1)
            ->get();
    }

    public function record_webhook_event($event_id, $event_type) {
        return $this->db->table('paymongo_webhook_events')->insert([
            'event_id' => $event_id,
            'event_type' => $event_type,
        ]);
    }

    public function create_offset($trip_id, $amount) {
        $this->db->table('bookings')->insert([
            'trip_id' => $trip_id,
            'payment_category' => 'carbon_offset',
            'amount' => $amount,
            'offset_amount' => $amount,
            'payment_status' => 'pending',
        ]);
        $id = $this->db->last_id();
        integration_queue_booking_status_email($this->db, $id, 'pending');
        return $id;
    }

    public function create_stay_booking($trip_id, $stay_id, $amount) {
        $this->db->table('bookings')->insert([
            'trip_id' => $trip_id,
            'stay_id' => $stay_id,
            'payment_category' => 'stay',
            'amount' => $amount,
            'offset_amount' => 0,
            'payment_status' => 'pending',
        ]);
        $id = $this->db->last_id();
        integration_queue_booking_status_email($this->db, $id, 'pending');
        return $id;
    }

    public function create_nearby_booking($trip_id, $reservation_name, $amount = 0) {
        $amount = (float) $amount;
        $status = $amount > 0 ? 'pending' : 'reservation';
        $this->db->table('bookings')->insert([
            'trip_id' => $trip_id,
            'reservation_name' => $reservation_name,
            'payment_category' => 'stay',
            'amount' => $amount,
            'offset_amount' => 0,
            'payment_status' => $status,
        ]);
        $id = $this->db->last_id();
        if ($amount > 0) integration_queue_booking_status_email($this->db, $id, 'pending');
        return $id;
    }

    public function create_trip_expense($trip_id, $category, $description, $amount) {
        $this->db->table('bookings')->insert([
            'trip_id' => $trip_id,
            'reservation_name' => $description,
            'payment_category' => $category,
            'amount' => $amount,
            'offset_amount' => $category === 'carbon_offset' ? $amount : 0,
            'payment_status' => 'pending',
        ]);
        $id = $this->db->last_id();
        integration_queue_booking_status_email($this->db, $id, 'pending');
        return $id;
    }

    public function set_payment_reference($id, $reference) {
        return $this->db->table('bookings')->where('id', $id)->update(['paymongo_ref' => $reference]);
    }

    public function set_status_by_id($id, $status) {
        if (!in_array($status, ['paid', 'failed', 'reservation'], true)) return false;
        return $this->db->table('bookings')->where('id', (int) $id)->update(['payment_status' => $status]);
    }

    public function set_status_by_reference($reference, $status) {
        if (!in_array($status, ['paid', 'failed'], true)) return false;
        $booking = $this->db->table('bookings')
            ->select('id')
            ->where('paymongo_ref', $reference)
            ->where('payment_status', 'pending')
            ->limit(1)
            ->get();
        if (!$booking) return false;

        $updated = $this->db->table('bookings')
            ->where('paymongo_ref', $reference)
            ->where('payment_status', 'pending')
            ->update(['payment_status' => $status]);
        if ($updated) integration_queue_booking_status_email($this->db, (int) $booking['id'], $status);
        return $updated;
    }
}