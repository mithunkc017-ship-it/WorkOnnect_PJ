-- Run this if you already imported database.sql/upgrade.sql before ratings were added
USE workerconnect;
CREATE TABLE IF NOT EXISTS reviews(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT UNIQUE,worker_id INT,customer_id INT,stars TINYINT,comment VARCHAR(255),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
