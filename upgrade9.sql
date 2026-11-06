-- Run if you already imported older SQL files (v11 features)
USE workerconnect;
ALTER TABLE users ADD wallet INT DEFAULT 0, ADD ref_code VARCHAR(12), ADD referred_by INT, ADD ref_rewarded TINYINT DEFAULT 0, ADD work_days VARCHAR(30), ADD work_from TIME, ADD work_to TIME, ADD area VARCHAR(150);
ALTER TABLE jobs ADD tip INT DEFAULT 0, ADD refunded TINYINT DEFAULT 0, ADD reschedule_slot DATETIME, ADD reschedule_by INT;
CREATE TABLE IF NOT EXISTS activity_log(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(200),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS wallet_tx(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,amount INT,note VARCHAR(100),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
