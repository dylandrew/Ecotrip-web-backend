ALTER TABLE reminders
  ADD COLUMN sms_gateway_ref VARCHAR(100) NULL;

CREATE TABLE IF NOT EXISTS sms_notifications (
  event_key VARCHAR(150) PRIMARY KEY,
  recipient VARCHAR(20) NOT NULL,
  message VARCHAR(480) NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  provider_ref VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  INDEX idx_sms_notifications_queue (status, next_attempt_at)
);
