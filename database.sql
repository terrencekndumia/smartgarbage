-- Smart Garbage Collection System - import in phpMyAdmin: Import tab -> choose file -> Go
-- WARNING: this resets the smart_garbage database (drops old tables).
CREATE DATABASE IF NOT EXISTS smart_garbage CHARACTER SET utf8mb4;
USE smart_garbage;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS bin_reports, alerts, notifications, collection_records, sensor_readings, garbage_bins, bins, users;
SET FOREIGN_KEY_CHECKS=1;

-- Admin (estate manager), resident and collector all live in users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  unit VARCHAR(30) NOT NULL DEFAULT '',
  role ENUM('admin','resident','collector') NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE garbage_bins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location VARCHAR(100) NOT NULL,
  capacity INT NOT NULL DEFAULT 120,
  resident_id INT NULL,
  fill_level INT NOT NULL DEFAULT 0,
  status ENUM('normal','needs_collection') NOT NULL DEFAULT 'normal',
  paid TINYINT(1) NOT NULL DEFAULT 0,
  paid_on DATETIME NULL,
  last_reading DATETIME NULL,
  FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE sensor_readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bin_id INT NOT NULL,
  fill_level INT NOT NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (bin_id, recorded_at),
  FOREIGN KEY (bin_id) REFERENCES garbage_bins(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bin_id INT NULL,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  status ENUM('unread','read') NOT NULL DEFAULT 'unread',
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id, id),
  FOREIGN KEY (bin_id) REFERENCES garbage_bins(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE collection_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bin_id INT NOT NULL,
  collector_id INT NULL,
  collection_date DATE NULL,
  status ENUM('requested','assigned','collected','cancelled') NOT NULL DEFAULT 'requested',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bin_id) REFERENCES garbage_bins(id) ON DELETE CASCADE,
  FOREIGN KEY (collector_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Damage reports sent by residents
CREATE TABLE bin_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bin_id INT NOT NULL,
  user_id INT NOT NULL,
  type VARCHAR(30) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('open','resolved') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  FOREIGN KEY (bin_id) REFERENCES garbage_bins(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Demo accounts (change these passwords later)
-- admin@estate.com / admin123   kamau@estate.com / collector123
-- jane@estate.com / resident123 john@estate.com / resident123
INSERT INTO users (id,name,email,phone,unit,role,password_hash) VALUES
 (1,'Estate Manager','admin@estate.com','0700000001','','admin','$2b$12$D2XcG6pALJjmgFisvs9vn.7oBpmRsmVSBcmaOEbWEubSC3k.ExMDm'),
 (2,'Kamau','kamau@estate.com','0700000002','','collector','$2b$12$hrJNxDvx1hCBj0/DjGcKEuoK6oG1uB81CUvlfETY.ZjObVABJnVoK'),
 (3,'Jane Wanjiru','jane@estate.com','0700000003','A1','resident','$2b$12$p10Smj9wrLQ3aCh66HDl3OyZea./3EFpIL8sma9vbnhV.RgKfWR.G'),
 (4,'John Otieno','john@estate.com','0700000004','B2','resident','$2b$12$L0aJ47O0HJwoEfRPEZ9Sx.fMtFxX1jmtfq9rKWZEiPGDY3iuVyGpm');

-- Bin 1 is the bin the Wokwi ESP32 reports for (BIN_ID = 1)
INSERT INTO garbage_bins (id,location,resident_id,fill_level,last_reading) VALUES
 (1,'Block 2',3,0,NOW()),
 (2,'Block 1',4,50,NOW());
