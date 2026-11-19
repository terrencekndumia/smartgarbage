-- Adds the damage-report table WITHOUT touching your existing data
USE smart_garbage;
CREATE TABLE IF NOT EXISTS bin_reports (
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

