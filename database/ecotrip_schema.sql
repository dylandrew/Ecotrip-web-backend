CREATE DATABASE IF NOT EXISTS ecotrip_lavalust_db CHARACTER SET utf8mb4;
USE ecotrip_lavalust_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  google_sub VARCHAR(255) NULL UNIQUE,
  phone VARCHAR(20),
  address VARCHAR(255) NULL,
  role ENUM('traveler','admin') DEFAULT 'traveler',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS trips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  destination VARCHAR(150) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  budget DECIMAL(10,2) NOT NULL,
  travelers INT DEFAULT 1,
  total_co2_kg DECIMAL(8,2) DEFAULT 0,
  status ENUM('draft','planned','booked','completed') DEFAULT 'draft',
  experience_notes TEXT NULL,
  experience_rating TINYINT UNSIGNED NULL,
  share_experience TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  location VARCHAR(150) NOT NULL,
  eco_rating TINYINT NOT NULL,          -- 0 = standard/not eco-rated; 1 to 5 = eco rating
  price_per_night DECIMAL(10,2) NOT NULL,
  certification VARCHAR(100),
  description TEXT
);

CREATE TABLE IF NOT EXISTS transport_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mode ENUM('bus','train','bike','walk','ferry','carpool') NOT NULL,
  origin VARCHAR(150) NOT NULL,
  destination VARCHAR(150) NOT NULL,
  distance_km DECIMAL(7,2),
  co2_kg_per_person DECIMAL(6,2),
  cost DECIMAL(10,2)
);

CREATE TABLE IF NOT EXISTS itinerary_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  day_number INT NOT NULL,
  item_type ENUM('transport','stay','activity') NOT NULL,
  stay_id INT NULL,
  transport_id INT NULL,
  description VARCHAR(255),
  start_time TIME,
  est_cost DECIMAL(10,2) DEFAULT 0,
  est_co2_kg DECIMAL(6,2) DEFAULT 0,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  experience_notes TEXT NULL,
  share_experience TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (stay_id) REFERENCES stays(id) ON DELETE SET NULL,
  FOREIGN KEY (transport_id) REFERENCES transport_options(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  stay_id INT NULL,
  reservation_name VARCHAR(150) NULL,
  payment_category VARCHAR(30) NOT NULL DEFAULT 'other',
  amount DECIMAL(10,2) NOT NULL,
  offset_amount DECIMAL(10,2) DEFAULT 0,
  paymongo_ref VARCHAR(100),
  payment_status ENUM('pending','paid','failed','refunded','reservation') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (stay_id) REFERENCES stays(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  photo_path VARCHAR(255) NOT NULL,
  caption VARCHAR(255),
  taken_at DATETIME,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS rate_limits (
  rate_key CHAR(64) PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  reset_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS reminders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  send_at DATETIME NOT NULL,
  status ENUM('pending','sent','failed') DEFAULT 'pending',
  sms_gateway_ref VARCHAR(100),
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS password_reset_otps (
  email VARCHAR(150) PRIMARY KEY,
  user_id INT NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  requested_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS paymongo_webhook_events (
  event_id VARCHAR(100) PRIMARY KEY,
  event_type VARCHAR(100) NOT NULL,
  processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
