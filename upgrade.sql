-- Run this ONLY if you already imported the old database.sql
USE workerconnect;
ALTER TABLE jobs ADD price INT DEFAULT 0, ADD paid TINYINT DEFAULT 0, ADD pay_method VARCHAR(20) NULL;
CREATE TABLE IF NOT EXISTS notifications(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,msg VARCHAR(200),link VARCHAR(100),seen TINYINT DEFAULT 0,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
