-- v19: offer stats, review replies, cancellation fee, announcements, reminders. Run if you already have the database (new installs: just import database.sql)
USE workerconnect;
ALTER TABLE reviews ADD reply VARCHAR(300), ADD reply_at DATETIME;
ALTER TABLE offers ADD created TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE jobs ADD rem24 TINYINT DEFAULT 0, ADD rem1 TINYINT DEFAULT 0, ADD cancel_fee INT DEFAULT 0;
CREATE TABLE IF NOT EXISTS announcements(id INT AUTO_INCREMENT PRIMARY KEY,msg VARCHAR(200),link VARCHAR(100),active TINYINT DEFAULT 1,created TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
