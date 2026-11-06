-- v22: settlements, invoice date, work photos, login history. Run if you already have the database (new installs: just import database.sql)
USE workerconnect;
ALTER TABLE jobs ADD paid_at DATETIME, ADD settled TINYINT DEFAULT 0, ADD settled_at DATETIME, ADD settle_ref VARCHAR(40);
CREATE TABLE IF NOT EXISTS job_photos(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT,user_id INT,kind ENUM('before','after'),photo VARCHAR(200),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS login_history(id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,email VARCHAR(100),ip VARCHAR(45),ua VARCHAR(120),ok TINYINT,note VARCHAR(40),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
