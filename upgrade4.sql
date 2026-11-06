-- Run if you already imported older SQL files (v5 features)
USE workerconnect;
CREATE TABLE IF NOT EXISTS categories(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(40) UNIQUE);
INSERT IGNORE INTO categories(name)VALUES('Electrician'),('Plumber'),('Carpenter'),('Painter'),('Cleaner'),('Driver'),('Mechanic'),('AC Technician');
CREATE TABLE IF NOT EXISTS complaints(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT,user_id INT,msg VARCHAR(300),status ENUM('open','resolved') DEFAULT 'open',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS password_resets(id INT AUTO_INCREMENT PRIMARY KEY,email VARCHAR(100),token VARCHAR(40),expires DATETIME);
