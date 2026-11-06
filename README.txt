=== MONGODB SETUP (the database is now MongoDB - this replaces the MySQL / phpMyAdmin steps below) ===
1) Install MongoDB Community Server (https://www.mongodb.com/try/download/community) and start it (Windows: it runs as a service on port 27017).
   Or create a free MongoDB Atlas cluster and put its connection string in MONGO_URI (settings.php).
2) Install the PHP "mongodb" extension (XAMPP, Windows):
   - Check your PHP version/thread-safety: open http://localhost/dashboard/phpinfo.php  (e.g. PHP 8.2, "Thread Safety: enabled", x64)
   - Download the matching php_mongodb.dll from https://pecl.php.net/package/mongodb  (Windows DLL column), copy it into C:\xampp\php\ext\
   - In C:\xampp\php\php.ini add the line:  extension=mongodb   then restart Apache.
   (Linux: sudo pecl install mongodb, then add extension=mongodb.so to php.ini.)  No Composer packages are needed.
3) settings.php: MONGO_URI (default mongodb://127.0.0.1:27017) and MONGO_DB (default workonet) - the database is created automatically.
4) Open http://localhost/workonet/mongo_setup.php once (creates indexes + default categories; also runs automatically on first visit).
5) Register on the site, then make yourself admin:  php mongo_make_admin.php you@example.com   (replaces the old UPDATE users SET role='admin' ...)
6) Already have data in the old MySQL database?  php mongo_migrate_from_mysql.php localhost workerconnect root ""   copies everything (ids are kept).
How it works: config.php still creates $pdo, but it is now a MongoPDO object (mongo_db.php). It reads the same SQL the pages use and runs it
on MongoDB collections (one per old table; layout in mongo_schema.php). So no page had to change. Notes:
- database.sql / upgrade*.sql are NOT used any more (kept only for reference and for the MySQL migration).
- A standalone MongoDB server has no multi-document transactions: payments use atomic single-document updates (a job can still be paid only once),
  and if a payment fails half-way the earlier steps are undone (rollBack). To also get real isolation use an Atlas / replica-set deployment.
- Text is compared case-insensitively like MySQL did (e.g. e-mail login). Big reports load the needed collections into PHP memory: fine for
  a project/college-size database, use real MongoDB aggregation pipelines if you ever need millions of rows.
- Admin password/e-mail codes etc. work exactly as before. Rows keep their numeric ids, so links like job.php?id=5 still work.

SETUP (XAMPP): 1) Copy this folder to C:\xampp\htdocs\workonet  2) Start Apache + MySQL
3) phpMyAdmin > Import > database.sql  4) Open http://localhost/workonet
5) Register, then in phpMyAdmin run: UPDATE users SET role='admin' WHERE email='your@email.com';

UPDATE v2: Already imported the old database? Import upgrade.sql instead of database.sql.
AI chat: works offline with simple answers. For real AI, paste your Anthropic API key in config.php (AI_KEY).
Payments are a DEMO (no real money, details not saved).

UPDATE v3 (ratings, search, worker profiles): if you already imported earlier SQL files, import upgrade2.sql in phpMyAdmin.

UPDATE v4 (job chat, cancel/edit/reject, analytics, worker profile, dark mode): if you already imported older SQL, import upgrade3.sql.

UPDATE v5 (invoice, earnings, complaints, admin categories, forgot password): if you already imported older SQL, import upgrade4.sql.

UPDATE v6 (security: CSRF/login limit/upload checks, emergency jobs, 10% platform commission): if you already imported older SQL, import upgrade5.sql.

UPDATE v7 (worker ID verification, availability, favorites, Top Rated badge): if you already imported older SQL, import upgrade6.sql.

UPDATE v8 (Kannada/English switch, live chat refresh, Google Maps): no database change needed. Maps need an internet connection.

UPDATE v9 (fixes + renamed to workonet): already imported older SQL? Import upgrade7.sql. Fresh setup: import only database.sql
(the database is still named "workerconnect" inside MySQL, that is fine).
Forgot password: the reset email text is saved in mail_log.txt in the project folder (localhost has no mail server).

UPDATE v10 (hire directly, nearby workers, booking time, confirm completion, portfolio, price list, ban users, CSV export, coupons, help/contact):
already imported older SQL? Import upgrade8.sql. Fresh setup: import only database.sql.

UPDATE v11 (tip, refund, working hours, reschedule, repeat booking, levels, activity log, wallet, job templates, referral codes): renamed to workonet.
Already imported older SQL? Import upgrade9.sql. Fresh setup: import only database.sql. URL: http://localhost/workonet

UPDATE v12 (new theme + animations + notification bar): no database change. The bell in the menu opens a panel, shows popups for new notifications and refreshes every 15 seconds.

UPDATE v13 (new "Aurora Workshop" background + homepage upgrade): no database change. Animated glow, gear pattern and dot grid (light + dark mode),
hero search by service/area, live stats counter, clickable service cards, "Why workonet" features, customer reviews, FAQ, back-to-top button.

UPDATE v14 (5 new features) - run upgrade10.sql if you already have the database (new installs: just import database.sql)
2. Razorpay payments (pay.php): put your TEST keys in settings.php (RZP_KEY / RZP_SECRET). Without keys the old demo payment still works.
   Payment is verified on the server (HMAC signature) and finished in one database transaction (no double payment).
3. Email OTP verification (verify_email.php): 6-digit code, 10 minutes, 5 tries. Without SMTP the code is shown on screen (demo) and saved in mail_log.txt.
   For real email: fill SMTP_* in settings.php (Gmail + App Password) and set SHOW_OTP_DEMO to false.
   Admin account: register, verify the code, then run: UPDATE users SET role='admin' WHERE email='you@example.com';
4. Installable app (PWA): manifest.json + sw.js + icons. Works on localhost or HTTPS. Browser notification when a new alert arrives.
5. Kannada: choose ಕನ್ನಡ in the language switch. lang_kn.php translates almost every page. Add more lines there to translate more text.

UPDATE v15 (design like the Workonnect sign-up screenshot + responsive)
- New teal colour theme and soft pastel background for the whole site; site name is now Workonnect.
- Sign-up and login are centred cards (confirm password, show/hide, password-strength bar, terms checkbox, "Creating..." button). Same style for forgot/reset/verify pages.
- terms.php and privacy.php added (sample text - get it reviewed before real use).
- Works on laptop and phone: menu becomes a hamburger on small screens, tables scroll sideways, forms and cards resize automatically.
- Heading font is "Gaegu" (Google Fonts, needs internet). Change --display at the top of the v15 block in style.css to use another.

UPDATE v16 (15 new features) - run upgrade11.sql if you already have the database (new installs: just import database.sql)
1. Itemized quote (job page): worker sends labour + materials, customer approves, price updates.
2. Extra work: worker proposes extra work during the job, customer approves before it is added to the price.
4. "I am on the way" button for the worker.
5. SOS button (alerts the admin) + share link (share.php) so a trusted person can see the job status.
6. Photos in the job chat.
7. Smart Match (match.php): ranks workers by rating, verification, availability, experience and distance.
8. Address book (addresses.php) + saved-address dropdown when posting a job.
9. Leaderboard (leaderboard.php) with "Worker of the Month".
10. Admin charts (charts.php): jobs and income per month, jobs by status.
12. New-job alerts: workers with the matching skill are notified; each worker can switch alerts ON/OFF on the dashboard.
13. Worker schedule (schedule.php) + double-booking warning (jobs less than 2 hours apart).
14. Price guide on the post-job form: average price of paid jobs in the chosen category.
15. Auto-expire: open jobs with no worker for 14 days are cancelled and the customer is notified (checked when the dashboard opens).
New text is in English; add lines to lang_kn.php to translate it into Kannada.

UPDATE v17 (7 new features) - run upgrade12.sql if you already have the database (new installs: just import database.sql)
1. Escrow: on a job with an assigned worker the customer can pay early ("Pay securely now"). The money is held and only counts in the worker's earnings after the customer confirms the work.
2. Dispute center (dispute.php): either side opens a dispute, both add evidence (text + photo), payment is held, admin decides: refund customer / release to worker / dismiss. Admin list: disputes.php.
3. AI job assistant (post a job page): describe the problem (+ optional photo) and the form is filled with a title, category, details, price range and checklist. Real AI needs AI_KEY in config.php; without it a simple keyword mode is used.
4. Live worker tracking: the worker taps "Share my live location" on the job page; the customer sees the position on a map (updates every 10 seconds).
5. Demand heatmap (heatmap.php) for workers and admin: which areas and services have the most requests.
6. Hidden phone numbers: a worker sees the customer's number only after the job is assigned to them (customer sees the worker as before).
7. Skill certificates (certificates.php): workers upload, admin approves, a "Certified" line appears on the public profile.

UPDATE v18 - run upgrade13.sql if you already have the database (it only drops the old payouts table)
1. Payout system removed (payouts.php, menu links, balance code and payouts table). Earnings are still shown on the Earnings page.
2. Open jobs are now the FIRST section of the worker dashboard (search + category filter), above "My Assigned Jobs".
3. New separate page open_jobs.php (menu: "Open Jobs"): all open jobs with search, category, emergency-only, "my skill only", offer counts and paging.

UPDATE v19 (5 new features) - run upgrade14.sql if you already have the database (new installs: just import database.sql)
1. Offer comparison table on the job page: price, rating, jobs done, distance (when the job location is a GPS pin), tags "Best value / Lowest price / Highest rated", Accept/Reject buttons.
2. Review replies: the worker can reply once to each review on their profile. Profile also shows "hired on X% of offers" and the usual reply time.
3. Cancellation policy: free until a worker accepts. After that 5% of the price (min Rs20), or 20% (min Rs50) if the visit is within 24 hours or the worker is on the way. 90% of the fee goes to the worker. The fee is taken from the customer's wallet (or from the escrow refund). Text added to terms.php.
4. Announcements (admin): a banner at the top of every page that users can close. Admin dashboard > Announcements.
5. Reminders: for assigned jobs with a booked time slot, both sides get a notification 24 hours and 1 hour before (checked by the notification bar, at most once a minute).

UPDATE v20
- Removed from the job page: "Request free re-visit" (7-day warranty) and "Repeat automatically" (subscriptions), including subscriptions.php, the dashboard link and the recurring-booking code. upgrade15.sql (optional) drops the old subscriptions table.
- Completed the v19 review replies (reply form on the worker's own profile) and the admin "Announcements" button, which were missing from the first v19 zip.

UPDATE v21 - QR payment (run upgrade16.sql if you already have the database; new installs: just import database.sql)
- Pay page now shows the breakdown: job amount, platform fee (10%, kept by the platform) and what the worker receives.
- New payment method "Scan QR code": the customer scans a UPI QR (PhonePe / Google Pay / Paytm...), pays the exact amount to the platform's UPI ID, then types the 12-digit UPI reference (UTR) and taps "I have paid".
- The admin checks the money in the bank/UPI app and confirms under "QR payments". Only then the job is marked paid, the platform keeps the commission and the worker's share is recorded (escrow rules still apply).
- Set YOUR UPI ID: add this line to your settings.php  ->  define('PLATFORM_UPI','yourname@okhdfcbank');   (default 'yourname@upi' shows a demo QR)
- The QR image is drawn in the browser by the local file qrcode.js (MIT licence), so it works without internet.

UPDATE v22 (5 features) - run upgrade17.sql if you already have the database (new installs: just import database.sql)
1. Settlements (admin > Settlements): lists what each worker should receive (paid jobs, escrow released, no dispute, minus 10% fee). After you pay the worker, enter the payment reference and press "Mark as paid". The worker sees "Paid on <date> (ref)" or "Pending" on the Earnings page and gets a notification.
2. Invoice now shows service amount, coupon, tip, platform fee, worker share, payment reference and paid date (print / save as PDF).
3. Before/after photos: the worker uploads "before" and "after" photos on the job page; the customer is notified of the after photo.
4. Slot booking: on the Hire page the customer picks a date and then a free hourly slot inside the worker's working hours; booked slots are greyed out and double booking is blocked.
5. Admin two-step login: after the password the admin must enter a 6-digit e-mail code (admin_2fa.php). Admin > Login history lists every successful and failed login with IP and browser. To turn 2-step off add define('ADMIN_2FA',false); to settings.php.

UPDATE v23 - worker UPI ID + admin UPI ID (platform fee) + split UPI payment. Run upgrade18.sql if you already have the database (new installs: just import database.sql)
- Workers: "💳 Add UPI ID" on the dashboard (my_upi.php). It is where customers can pay the worker's share directly, and where the admin pays earnings.
- Admin: "💳 My UPI ID" on the admin dashboard. The UPI ID in the ADMIN profile receives the platform fee (commission) and the single-QR payments. (settings.php PLATFORM_UPI is only the fallback.)
- New payment method "Pay worker directly + platform fee (2 QR codes)" (after the job is completed and confirmed, and only if the worker has a UPI ID):
  QR 1 = worker's share -> worker's UPI ID, QR 2 = 10% platform fee -> admin's UPI ID. The customer enters both UPI reference numbers (UTR).
  The worker confirms their money (upi_confirm.php), the admin confirms the fee (qr_payments.php). When both confirmed the job is paid and the worker's share is already settled.
- The old single QR (platform collects everything, then Settlements) still works. Settlements now shows the worker's UPI ID with a pay link / QR.

UPDATE v24 - new dashboard (no SQL changes)
- New landing page after login: dash.php (menu "Dashboard"): pill tab bar, KPI cards (blue hero card), smooth line charts, donut, funnel and leaderboard bars, tables with avatars and status pills, light/dark mode. Different content for admin, worker and customer. Charts are drawn with inline SVG (no library, works offline).
- Admin also gets analytics.php (12-month revenue, busiest hours/weekdays, fee by category, payment methods, top areas).
- The old list/management page is still there as the "Manage" / "My jobs" tab (dashboard.php).
- FIX: config.php had a '}' hidden inside a comment (banned-user line) which caused a PHP parse error. All 70 PHP files now pass 'php -l'.
