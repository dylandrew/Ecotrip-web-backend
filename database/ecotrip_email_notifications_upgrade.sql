CREATE TABLE IF NOT EXISTS email_notifications (
  event_key VARCHAR(150) PRIMARY KEY,
  recipient VARCHAR(150) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  last_error VARCHAR(255) NULL,
  INDEX idx_email_notifications_queue (status, next_attempt_at)
);
