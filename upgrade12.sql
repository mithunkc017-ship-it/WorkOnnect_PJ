-- v17: escrow, disputes, live tracking, certificates. Run if you already have the database (new installs: just import database.sql)
USE workerconnect;
ALTER TABLE jobs ADD escrow TINYINT DEFAULT 0, ADD disputed TINYINT DEFAULT 0, ADD wlat DOUBLE, ADD wlng DOUBLE, ADD wtime DATETIME;
CREATE TABLE IF NOT EXISTS disputes(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT,opened_by INT,reason VARCHAR(300),status ENUM('open','resolved') DEFAULT 'open',decision VARCHAR(20),admin_note VARCHAR(200),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS dispute_evidence(id INT AUTO_INCREMENT PRIMARY KEY,dispute_id INT,user_id INT,note VARCHAR(300),photo VARCHAR(200),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS certificates(id INT AUTO_INCREMENT PRIMARY KEY,worker_id INT,title VARCHAR(100),photo VARCHAR(200),status ENUM('pending','approved','rejected') DEFAULT 'pending',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
