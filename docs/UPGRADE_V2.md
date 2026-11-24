# Fast v2 improvements

- Employer directory now supports server-side country filtering, name/domain/industry search and 25-row pagination instead of loading the last 200 records.
- Optional database indexes are provided in `sql/performance_indexes.sql`. Apply only after backup and review; on a fresh database they can also be added.
- Existing database and sending logic are preserved. This is a development build, not live-hosting verified.
- For Hostinger: copy files in `public/` to your subdomain document root and keep `private/` outside web root. Never overwrite your live `private/config.php` with an example file. Back up DB and files first.
- Discovery still requires Brave API access and manual evidence review. Sending requires verified authorization, Brevo credentials and policy compliance. No unsolicited bulk sending.
- Test login, filtering, CSV export, CSRF, approval, suppression, and controlled send with test recipients before production.
