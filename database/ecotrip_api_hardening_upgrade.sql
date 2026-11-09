CREATE TABLE IF NOT EXISTS rate_limits (
  rate_key CHAR(64) PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(50) NULL,
  target_id INT NULL,
  ip VARCHAR(45) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

SET @has_paymongo_ref_index = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'bookings'
    AND index_name = 'idx_bookings_paymongo_ref'
);
SET @paymongo_ref_ddl = IF(
  @has_paymongo_ref_index > 0,
  'SELECT 1',
  'CREATE UNIQUE INDEX idx_bookings_paymongo_ref ON bookings (paymongo_ref)'
);
PREPARE ecotrip_paymongo_ref_stmt FROM @paymongo_ref_ddl;
EXECUTE ecotrip_paymongo_ref_stmt;
DEALLOCATE PREPARE ecotrip_paymongo_ref_stmt;

SET @has_payment_created_index = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'bookings'
    AND index_name = 'idx_bookings_payment_created'
);
SET @payment_created_ddl = IF(
  @has_payment_created_index > 0,
  'SELECT 1',
  'CREATE INDEX idx_bookings_payment_created ON bookings (payment_status, created_at)'
);
PREPARE ecotrip_payment_created_stmt FROM @payment_created_ddl;
EXECUTE ecotrip_payment_created_stmt;
DEALLOCATE PREPARE ecotrip_payment_created_stmt;
