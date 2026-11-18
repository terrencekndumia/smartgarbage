-- Import this in phpMyAdmin: Import tab -> choose file -> Go
CREATE DATABASE IF NOT EXISTS smart_garbage CHARACTER SET utf8mb4;
USE smart_garbage;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  role ENUM('admin','resident','collector') NOT NULL,
  password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS bins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  resident_id INT NOT NULL,
  house VARCHAR(50) NOT NULL,
  level INT NOT NULL DEFAULT 0,
  paid TINYINT(1) NOT NULL DEFAULT 0,
  paid_on DATETIME NULL,
  updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bin_id INT NOT NULL,
  status ENUM('open','done') NOT NULL DEFAULT 'open',
  created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  collected_by VARCHAR(100) NULL,
  done_on DATETIME NULL,
  FOREIGN KEY (bin_id) REFERENCES bins(id) ON DELETE CASCADE
);

-- First admin: admin@estate.com / admin123 (change it after logging in)
INSERT IGNORE INTO users (name, email, role, password_hash)
VALUES ('Estate Admin', 'admin@estate.com', 'admin', '$2b$12$vOxfh/sTe5K1OfNR9kw28e/Q1mmkAWuItg4eevMwIjx/hGHrOaINu');
