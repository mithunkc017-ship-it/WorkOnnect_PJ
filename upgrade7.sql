-- Run if you already imported older SQL files (v9 fixes)
USE workerconnect;
ALTER TABLE offers ADD status ENUM('pending','rejected') DEFAULT 'pending';
CREATE TABLE IF NOT EXISTS login_attempts(id INT AUTO_INCREMENT PRIMARY KEY,ip VARCHAR(45),ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
