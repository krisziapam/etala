# e-TALA clean review package

This package is a cleaned version of the **uploaded e-tala-main.zip**, not the earlier e-TALA-InfinityFree-Ready application.

## Changes
- Removed Git metadata and unrelated PDF/JPG from source directories.
- Kept required PHP, CSS, JS, logos, database schema and seed, and upload folder.
- Changed the example base URL to empty string for domain-root deployment.
- Removed CREATE DATABASE / USE etala statements so the schema can be imported into an InfinityFree-created database.
- Added server-side ownership checks for responder report views and photos.
- Kept protected includes, database, and uploads directories.

## Before deployment
1. **Back up your existing htdocs and MySQL database. Do not overwrite the old website.**
2. Create a NEW InfinityFree site/subdomain or isolated test directory and a NEW MySQL database. This version uses `user`, `incident_report`, etc. and is incompatible with the earlier `users`, `incident_reports` schema.
3. Upload the contents of this folder into the test site's `htdocs` (so index.php is at the site root).
4. Copy `includes/config.example.php` to `includes/config.php` on the server. Set db_host, db_name, db_user, db_pass from InfinityFree and base_url to '' at the domain root. Never publish this config.
5. Import `database/schema.sql` into the NEW empty database, then import `database/seed.sql` once.
6. This uploaded version has NO initial Admin setup page. An initial Admin must be provisioned securely before login can work. Do not use the old setup.php or old SQL on this new schema.
7. Test login, role permissions, report creation, photos, and exports with fictional data only.

## Security and review notes
- This is a review candidate, not a production-security certification. Further audit of authorization, exports, uploads, rate limits and data privacy is needed.
- Do not upload real patient or incident records to free hosting without appropriate authorization and safeguards.
- `uploads/.htaccess` blocks direct photo access; photo.php enforces login and ownership.
- Keep database backups separate from source backups.
