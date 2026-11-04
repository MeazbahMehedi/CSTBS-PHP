# Operations guide

## Configuration

Copy `config.example.php` to the ignored `config.local.php` and set the database connection, base path, campus timezone, and persistent secret. Environment variables `CSTBS_DB_HOST`, `CSTBS_DB_PORT`, `CSTBS_DB_NAME`, `CSTBS_DB_USER`, `CSTBS_DB_PASSWORD`, `CSTBS_BASE_PATH`, and `CSTBS_SECRET` override those values.

Generate a secret with `php -r "echo bin2hex(random_bytes(32));"`. Preserve it across updates so existing QR signatures stay valid. Keep private configuration, SQL backups, logs, and runtime data outside Git. Apache's supplied rules deny access to private application paths.

Run commands from the repository directory using XAMPP's PHP executable or add that executable to your terminal PATH.

## Administrator recovery

```powershell
php bin/console.php admin-key admin@campus.edu
php bin/console.php admin-reset admin@campus.edu
```

`admin-key` issues a key only for a pending administrator. `admin-reset` deliberately revokes the active password and sessions and issues a replacement key. Keys expire after 20 minutes and can be consumed once at `/admin/activate`.

Additional administrators are promoted through the Users page. Their initial activation key can be issued there. Active passwords are recovered through the explicit trusted operator command.

## Development demo

```powershell
php bin/console.php seed-events
$env:CSTBS_DEMO_PASSWORD = 'choose-a-private-demo-password'
php bin/demo.php
Remove-Item Env:CSTBS_DEMO_PASSWORD
```

The fictional accounts are `demo.student@campus.example`, `demo.classmate@campus.example`, `demo.staff@campus.example`, and `demo.admin@campus.example`. Each uses your supplied private password. Demo administrators are already activated. Demo seeding is disabled in production configuration and rejects existing accounts with conflicting credentials or roles.

## Notifications

Successful reservations and cancellations write notifications to `mail_outbox` in the same database transaction. Run the worker after configuration.

```powershell
php bin/console.php mail-work
```

Development `mail_transport=log` writes to the ignored `storage/mail.log`. Set `mail_transport=smtp` and configure `mail_host`, `mail_port`, `mail_username`, `mail_password`, and `mail_from` to deliver through SMTP with STARTTLS. Use Task Scheduler or your host's job runner for recurring worker execution.

Rows are processed with database row locks. Delivery failures are retried up to five attempts. Review failed outbox rows and restricted server logs. Email delivery has at-least-once behavior around process or network failures. Local tests verify notification queueing, and your actual SMTP credentials need an operational send test.

## Backup and restoration

```powershell
php bin/backup.php
```

The command takes a consistent InnoDB snapshot and writes a new SQL file beneath private `storage/backups`. Store a separate protected copy off the application machine. Back up `config.local.php` separately because it holds the signing secret and database credentials.

To restore, create an empty database in phpMyAdmin, then import the SQL backup into that empty database. Update private configuration to use the restored database and retain the original secret. Confirm row counts, users, tickets, and gate assignments before admitting students. The backup restore was verified against an empty MariaDB database during development.

For schema updates, stop application writes and take a backup before applying a reviewed change. Version 1 installation is repeatable with its own schema. Future schema versions require explicit migration work.

## Public hosting

Set `production=true`, use HTTPS and a dedicated database account, configure SMTP, and keep storage private. The production session cookie is Secure, HttpOnly, and SameSite=Lax. Set the campus timezone deliberately. Review Apache virtual-host, TLS, upload-size, and database connection settings for the intended host.

Use the supplied `.htaccess` protection or equivalent explicit virtual-host rules. Private PHP files, Composer dependencies, schema, tests, Git metadata, and storage must stay inaccessible over HTTP. Verify those paths after changing hosting configuration.

## Local PHP preview

For development outside Apache, set `base_path` to an empty string in private configuration and run `php -S 127.0.0.1:8080 router.php`. Open `http://127.0.0.1:8080`. XAMPP usage uses Apache and the configured subfolder path.
