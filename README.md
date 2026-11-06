# Workonnect

Workonnect is a PHP web app for connecting customers with local service
professionals. Customers can post jobs, compare worker offers, and follow job
updates. Workers can create profiles, find jobs, and manage their work. Admins
can review verification requests and manage marketplace activity.

## Features

- Search workers by service, name, and area; browse worker profiles and reviews.
- Customer job requests, worker offers, hiring, scheduling, and job updates.
- Worker ID verification, availability, ratings, and profile badges.
- Wallet, invoices, UPI/QR payment flows, settlements, and payment records.
- Admin tools for users, categories, announcements, disputes, and reports.
- English/Kannada interface, notifications, help pages, and installable PWA.
- Public information pages: [How it works](how-it-works.php),
  [Why Workonnect](why-workonnect.php), [Verified workers](verified-workers.php),
  and [Quick answers](quick-answers.php).

## Requirements

- PHP supported by your XAMPP installation.
- The PHP `mongodb` extension enabled for the PHP runtime used by Apache.
- MongoDB Community Server running locally, or a MongoDB Atlas connection.
- Apache with PHP enabled (XAMPP is one option).

The app uses MongoDB through the included `mongo_db.php` compatibility layer.
MySQL and phpMyAdmin are not required for a fresh installation.

## Run locally with XAMPP

1. Install and start MongoDB Community Server, or create an Atlas cluster.
2. Put the project folder at `C:\xampp\htdocs\workonet`.
3. Check the PHP version, thread safety, and architecture used by XAMPP at
   `http://localhost/dashboard/phpinfo.php`. Install the matching MongoDB PHP
   extension from [PECL](https://pecl.php.net/package/mongodb), enable it in
   XAMPP's `php.ini` with `extension=mongodb`, then restart Apache.
4. Review the local settings in `settings.php`. The default MongoDB URI is
   `mongodb://127.0.0.1:27017` and the database name is `workonet`.
5. Start Apache and MongoDB, then open
   `http://localhost/workonet/mongo_setup.php` once. The app also initializes
   the schema on the first visit.
6. Visit `http://localhost/workonet` and register an account.
7. To make your registered account an administrator, run from the project
   directory:

   ```powershell
   C:\xampp\php\php.exe mongo_make_admin.php your@email.com
   ```

   Use the same email address you registered with.

## Configuration

Update `settings.php` for local or deployment-specific configuration:

| Setting | Purpose |
| --- | --- |
| `MONGO_URI`, `MONGO_DB` | MongoDB connection and database name |
| `SMTP_*` | SMTP details for sending email |
| `SHOW_OTP_DEMO` | Shows email verification codes in demo mode when SMTP is not configured |
| `RZP_KEY`, `RZP_SECRET` | Razorpay API credentials; use test credentials for development |
| `PLATFORM_UPI`, `PLATFORM_NAME` | Platform UPI payment details |

Keep real credentials and payment details private. Do not commit production
secrets. On a local demo without SMTP, email content is written to `mail_log.txt`;
demo OTP display is enabled by default.

## Payments and deployment

Payment integrations depend on valid provider configuration. Use Razorpay test
keys for development, and verify UPI/QR payments in the configured account.
Do not treat a local demo as a production payment setup.

Before deploying, configure HTTPS, SMTP, MongoDB access controls, and real
payment credentials securely. Turn off demo OTP display, review the sample
terms and privacy pages, and test registration, job creation, offers, and
payment flows in the deployment environment.

## Project layout

- `index.php` — public landing page.
- `config.php` — shared initialization, navigation, helpers, and page layout.
- `settings.php` — local application and integration settings.
- `mongo_db.php`, `mongo_schema.php` — MongoDB adapter and schema.
- `mongo_setup.php` — initialize MongoDB indexes and default categories.
- `mongo_make_admin.php` — promote a registered account to administrator.
- `how-it-works.php`, `why-workonnect.php`, `verified-workers.php`,
  `quick-answers.php` — public information pages.
- `style.css`, `dash.css` — shared and dashboard styles.
- `manifest.json`, `sw.js`, `icons/` — installable web app resources.

## Existing MySQL data

The application no longer uses MySQL for normal operation. If migrating an
older Workonnect database, see the MongoDB migration instructions in
[README.txt](README.txt) and review `mongo_migrate_from_mysql.php` before
running it.
