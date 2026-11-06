<?php // Your private settings. Leave empty to run in DEMO mode (works on localhost with no accounts).
define('SMTP_HOST','');      // e.g. smtp.gmail.com  (empty = emails are only saved in mail_log.txt)
define('SMTP_PORT',587);     // 587 (STARTTLS) or 465 (SSL)
define('SMTP_USER','');      // your Gmail address
define('SMTP_PASS','');      // Gmail "App Password" (Google Account > Security > App passwords), not your normal password
define('SMTP_FROM','');      // sender address, usually same as SMTP_USER
define('RZP_KEY','');        // Razorpay TEST key id, looks like rzp_test_xxxxxxxx (dashboard.razorpay.com > Settings > API Keys)
define('RZP_SECRET','');     // Razorpay TEST key secret
define('SHOW_OTP_DEMO',true);// when SMTP is empty, show the OTP on screen so you can demo it. Set false when deployed.
define('PLATFORM_UPI','yourname@upi'); // YOUR real UPI ID (e.g. name@okhdfcbank). Customers scan the QR and the money comes to this account; the platform keeps the 10% fee
define('PLATFORM_NAME','Workonnect');  // name shown in the customer's UPI app
define('MONGO_URI','mongodb://127.0.0.1:27017'); // MongoDB connection string. Atlas: mongodb+srv://user:password@cluster0.xxxxx.mongodb.net  (needs the PHP mongodb extension)
define('MONGO_DB','workonet');                 // database name (created automatically)
