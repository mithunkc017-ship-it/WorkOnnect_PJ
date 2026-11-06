-- v21: QR (UPI) payments. Run if you already have the database (new installs: just import database.sql)
USE workerconnect;
CREATE TABLE IF NOT EXISTS qr_payments(id INT AUTO_INCREMENT PRIMARY KEY,job_id INT,user_id INT,amount INT,discount INT DEFAULT 0,coupon VARCHAR(20),utr VARCHAR(20),status ENUM('waiting','submitted','approved','rejected') DEFAULT 'waiting',created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
