-- Run if you already imported older SQL files (v7 features)
USE workerconnect;
ALTER TABLE users ADD available TINYINT DEFAULT 1;
CREATE TABLE IF NOT EXISTS verifications(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,doc VARCHAR(200),status ENUM('pending','approved','rejected') DEFAULT 'pending',note VARCHAR(200),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS favorites(customer_id INT,worker_id INT,UNIQUE(customer_id,worker_id));
