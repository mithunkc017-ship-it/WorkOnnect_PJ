-- Run if you already imported older SQL files (v3 features)
USE workerconnect;
ALTER TABLE users ADD experience VARCHAR(60), ADD bio VARCHAR(255), ADD photo VARCHAR(200);
ALTER TABLE jobs MODIFY status ENUM('open','assigned','in_progress','completed','cancelled') DEFAULT 'open';
CREATE TABLE IF NOT EXISTS messages(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT,sender_id INT,msg VARCHAR(300),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
