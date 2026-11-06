-- Run if you already imported older SQL files (v10 features)
USE workerconnect;
ALTER TABLE users ADD lat DOUBLE, ADD lng DOUBLE, ADD banned TINYINT DEFAULT 0, ADD ban_reason VARCHAR(200);
ALTER TABLE jobs ADD slot DATETIME, ADD confirmed TINYINT DEFAULT 0, ADD discount INT DEFAULT 0;
ALTER TABLE jobs MODIFY status ENUM('open','requested','assigned','in_progress','completed','cancelled') DEFAULT 'open';
CREATE TABLE IF NOT EXISTS portfolio(id INT AUTO_INCREMENT PRIMARY KEY,worker_id INT,photo VARCHAR(200),caption VARCHAR(100));
CREATE TABLE IF NOT EXISTS service_prices(id INT AUTO_INCREMENT PRIMARY KEY,worker_id INT,service VARCHAR(60),price INT);
CREATE TABLE IF NOT EXISTS coupons(code VARCHAR(20) PRIMARY KEY,percent TINYINT,active TINYINT DEFAULT 1,used INT DEFAULT 0);
CREATE TABLE IF NOT EXISTS contacts(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80),email VARCHAR(100),msg VARCHAR(500),created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
