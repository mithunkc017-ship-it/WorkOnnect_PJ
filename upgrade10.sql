-- v14: email OTP verification, Razorpay reference. Run if you imported older SQL files.
USE workerconnect;
ALTER TABLE users ADD email_verified TINYINT DEFAULT 0, ADD otp VARCHAR(64), ADD otp_expires DATETIME, ADD otp_tries TINYINT DEFAULT 0;
UPDATE users SET email_verified=1; -- existing users are treated as verified
ALTER TABLE jobs ADD pay_ref VARCHAR(40);
