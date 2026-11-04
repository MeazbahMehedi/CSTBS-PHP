# Security

Report suspected vulnerabilities privately to the repository maintainer through GitHub before publishing credentials or attendee information.

## Protected boundaries

- PDO prepared statements and escaped PHP views.
- CSRF validation on every POST and session regeneration after authentication.
- Database-backed rate limits, hashed passwords, role checks, and session revocation.
- Persistent HMAC signing for opaque QR credentials.
- Transactional reservations and unique active-seat and attendee indexes.
- Staff-only explicit check-in with gate and ticket-state validation.
- Private exports, CSV formula escaping, bounded QR uploads, and protected runtime files.

## Deployment checks

Use HTTPS and production settings for a public deployment. Preserve the signing secret, protect database credentials, restrict the database account, and verify Apache access rules. Test camera behavior and SMTP delivery on the intended infrastructure. Run backups and verify restores regularly.

The separate PHP/MariaDB implementation requires a reviewed data migration before importing an existing Flask/SQLite installation.
