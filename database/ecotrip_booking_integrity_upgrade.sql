ALTER TABLE bookings MODIFY payment_status ENUM('pending','paid','failed','refunded','reservation') DEFAULT 'pending';
CREATE INDEX idx_bookings_payment_created ON bookings (payment_status, created_at);
CREATE UNIQUE INDEX idx_bookings_paymongo_ref ON bookings (paymongo_ref);
