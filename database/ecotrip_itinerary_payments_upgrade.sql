ALTER TABLE itinerary_items
  ADD COLUMN completed TINYINT(1) NOT NULL DEFAULT 0 AFTER est_co2_kg,
  ADD COLUMN experience_notes TEXT NULL AFTER completed,
  ADD COLUMN share_experience TINYINT(1) NOT NULL DEFAULT 0 AFTER experience_notes;

ALTER TABLE bookings
  ADD COLUMN payment_category VARCHAR(30) NOT NULL DEFAULT 'other' AFTER reservation_name;

UPDATE bookings
SET payment_category = CASE
  WHEN stay_id IS NOT NULL THEN 'stay'
  WHEN offset_amount > 0 THEN 'carbon_offset'
  ELSE 'other'
END;
