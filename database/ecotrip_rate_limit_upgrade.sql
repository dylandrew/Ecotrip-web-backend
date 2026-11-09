ALTER TABLE bookings MODIFY payment_status ENUM('pending','paid','failed','refunded','reservation') DEFAULT 'pending';
CREATE TABLE IF NOT EXISTS rate_limits (
  rate_key CHAR(64) PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);
