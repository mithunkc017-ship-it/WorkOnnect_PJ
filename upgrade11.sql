-- v16: 15 new features. Run if you already have the database (new installs: just import database.sql)
USE workerconnect;
ALTER TABLE jobs ADD quote_labour INT DEFAULT 0, ADD quote_mat INT DEFAULT 0, ADD quote_status ENUM('none','proposed','approved') DEFAULT 'none', ADD onway TINYINT DEFAULT 0, ADD done_at DATETIME, ADD revisit TINYINT DEFAULT 0, ADD revisit_note VARCHAR(200), ADD share_token VARCHAR(32);
ALTER TABLE messages ADD photo VARCHAR(200);
ALTER TABLE users ADD job_alerts TINYINT DEFAULT 1;
CREATE TABLE IF NOT EXISTS job_extras(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT,descr VARCHAR(100),amount INT,status ENUM('proposed','approved','declined') DEFAULT 'proposed');
CREATE TABLE IF NOT EXISTS addresses(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,label VARCHAR(30),address VARCHAR(150));
