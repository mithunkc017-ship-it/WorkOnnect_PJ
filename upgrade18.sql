-- v23: worker UPI ID, admin UPI ID (platform fee) and split UPI payments. Run if you already have the database (new installs: just import database.sql)
USE workerconnect;
ALTER TABLE users ADD upi_id VARCHAR(60);
ALTER TABLE qr_payments ADD mode ENUM('platform','split') DEFAULT 'platform', ADD worker_utr VARCHAR(20), ADD fee_utr VARCHAR(20), ADD worker_ok TINYINT DEFAULT 0, ADD fee_ok TINYINT DEFAULT 0;
