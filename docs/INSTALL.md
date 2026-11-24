# GJW Employer Connect AI — Hostinger PHP edition

## Status
Functional code package for PHP 8.2+ / MySQL with manual employer management, licensed Brave Search discovery, contact permission records, editable recruitment proposal drafts, controlled Brevo sending, CRM notes, audit log, and CSV export. This is **not a production-certified application**. No real API, live sending, or Hostinger deployment has been tested here. Reply inbox sync, PDF attachments, automatic contact discovery, true mailbox verification, webhooks, multi-user permissions and production-grade anti-abuse controls are NOT implemented. Drafts use a fixed template unless an OpenAI API key is configured.

## Setup
1. Create a dedicated subdomain/website in Hostinger hPanel and enable HTTPS. Ensure PHP 8.2+ with PDO MySQL, curl and mbstring extensions.
2. Create a MySQL database and import `sql/schema.sql` using phpMyAdmin.
3. Copy `private/config.example.php` to `private/config.php`. Use the full Hostinger database name and username (including prefixes).
4. Generate password hash locally with `php -r "echo password_hash('A_UNIQUE_STRONG_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"` and paste hash into config. Generate two DIFFERENT random secrets with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` for app_key and cron_token.
5. Upload `public/` as the subdomain's document root and `private/` OUTSIDE the public document root. `sql/` and `docs/` should not be publicly uploaded. The project uses `dirname(__DIR__).'/private/bootstrap.php'`, so place `private` as a sibling to the document root, or adjust that require path safely. Do not upload `legacy_phase1.php`.
6. Login using admin_email and chosen password. Test company add, contacts, drafts and CSV first.
7. For licensed discovery, purchase/configure Brave Search API credentials in `private/config.php`; the app uses `https://api.search.brave.com/res/v1/web/search`. Review API license, rate limits and suitability before use. Search results may be job boards, not actual employer websites; manual validation is mandatory.
8. For sending, configure a Brevo account, verified sender and transactional email API key. Confirm that your use case is permitted under its acceptable-use policy. `brevo_api_key`, `sender_email` and `sender_name` are set in config. Verify the domain with SPF/DKIM/DMARC. Do not use this for unsolicited mass mail.
9. Contacts start with `unknown` permission and `unverified` validation. Only mark `confirmed` and `valid` after independent evidence and a genuine check. Record evidence and basis. Review and approve every proposal; replace placeholder contact details. Revocations automatically suppress further sending.
10. Optional cron: run via **CLI only** (not URL) using `php /ABSOLUTE/PATH/TO/public/cron.php YOUR_CRON_TOKEN discovery` or `... send`. Configure via hPanel Cron Jobs if CLI supported. Keep secrets out of shared logs; use a protected wrapper script or environment secrets in production.

## Security/operations warnings
- Use HTTPS, secure sessions, private configuration, least-privilege DB user, strong admin password, hPanel MFA and daily backups.
- Single-admin demo login does not implement production login rate limiting, MFA, password recovery, per-user roles, webhook signature checks, or external security review.
- Sending is capped per batch and day; provider acceptance does not prove final delivery. Failed sends are NOT retried automatically because timeout outcomes can be ambiguous.
- The sample uses `DATE(created_at)` and server timezone. Configure timezone intentionally and validate provider limits.
- Shared hosting cron may not run frequently and can time out; a VPS or managed scheduler may be needed for scale.
- No automatic scraping of email addresses is included. Public email availability alone is not permission to contact.

## Optional AI draft generation
Add `openai_api_key` in `private/config.php` to enable the OpenAI API for drafting only. Set `openai_model` to an available JSON-mode-compatible model; provider charges apply. Without an API key the app falls back to a deterministic template. AI content is always a draft requiring human review. No sending occurs merely by generating a draft.

## Pre-launch validation
Run `php -l` on every PHP file, import SQL into a test database, exercise login/CSRF/permission/opt-out paths, test with consented addresses, confirm mail provider authorization and check the Hostinger CLI cron path. No real-world production test has been completed in this package.
