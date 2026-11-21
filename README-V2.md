# e-TALA Version 2 — PHP/MySQL source package

This is an **updated copy of the uploaded `e-TALA-clean-review.zip`**, not the earlier separately deployed `/public/` application. The clean-review package uses `user`, `incident_report`, `vehicle` tables and a **root `index.php`**. The earlier deployed application reportedly uses a different structure and schema. **Do not upload this ZIP directly over the current live site.**

## What is implemented

- Existing clean-review login, session checks, Admin and Responder accounts, incident CRUD, photos, profile, reports, dashboard and Today reports are preserved.
- Navy horizontal top navigation with hover and click dropdowns; separate Admin/Responder menus.
- `Backup Assistance → View Backup Requests / Request Backup`: create incident-linked or standalone requests; track accepted personnel/resource slots; accept concurrently with MySQL row locks; advance request status.
- Incident handover: request, receiving responder acceptance/rejection, cancellation, responsibility reassignment on acceptance, event timeline on incident details.
- `Today's Live Operations`: PHP JSON endpoint + 15-second AJAX polling, filterable event feed and connection status.
- Admin Dashboard: existing charts/stats preserved, operational glimpse, incident calendar with links to authorized reports, informational Open-Meteo weather search.
- Equipment inventory, low-stock indicators, responder equipment requests, Admin approval with stock deduction and immutable stock transaction records.
- Vehicle registry display, Admin fuel transaction entry and maintenance issue records.
- Emergency directory starter with national 911 hotline, placeholders for locally verified hospitals, Admin-only responder name list.
- Admin incident analytics and operational audit-event listing.

## Not yet implemented / not production-ready

- This is a **development build**, not an audited production release. Full end-to-end testing on your actual MySQL server has **not** been performed.
- The existing incident form and legacy report CRUD are preserved, not redesigned into a new workflow. Some existing legacy Today report links may be inaccessible to responders unless they own/are assigned the incident.
- Notification bell links to the Today operations feed; it does **not** yet provide per-user unread badges, read receipts, browser push notifications, or sound alerts. AJAX updates only while Today page is open.
- Weather search uses a geocoding result preference for exact Philippine city matches; it does not yet present a full disambiguation/autocomplete chooser.
- No official local emergency directory/hospital CRUD, responder phone publication, resource return acknowledgment, vehicle assignment approval, fuel receipt uploads or fuel approval workflow.
- Equipment and vehicle maintenance are initial modules; comprehensive inventory adjustment/returns, vehicle registry CRUD and fuel accounting controls remain future work.
- Audit log records **new V2 operational actions**, not every action in the original incident/user CRUD. Calendar is a month overview, not a fully filtered interactive planner.
- For emergency response, never depend solely on a web notification. Continue using established radio/phone dispatch procedures.

## Safe installation on XAMPP / separate test site

1. **Back up your existing project folder and export your database** in phpMyAdmin.
2. Create a separate folder, e.g. `htdocs/etala-v2-test/`, and extract this package's **contents** there.
3. Create a new empty MySQL database in phpMyAdmin. Import `database/schema.sql` then `database/seed.sql` (seed only once), then `database/upgrade-v2.sql`.
4. Copy `includes/config.example.php` to `includes/config.php`; set database credentials and `base_url` to `/etala-v2-test` on XAMPP. `includes/config.php` is intentionally absent from the ZIP.
5. **Create an initial Admin account securely** by running `php tools/create-first-admin.php` from a local terminal (CLI only). It refuses to create a second Admin. Record the generated temporary password privately and change it after first login. Do not use setup scripts from the incompatible live schema.
6. Start Apache and MySQL; visit `http://localhost/etala-v2-test/` and sign in. Verify Admin and Responder roles separately.
7. Test backup create/accept (including two simultaneous browsers), incident handover, incident details, equipment approval, Today feed, and Admin-only permissions. Check `api-operations.php` and `api-calendar.php` return JSON while logged in.
8. Only after verifying the **actual live schema and directory layout** should you plan a migration for your existing InfinityFree site. Never import `schema.sql` over an existing populated site.

## Upgrading an EXISTING database with this exact clean-review schema

- Back up database first; run **only** `database/upgrade-v2.sql` once in phpMyAdmin.
- Do not import `database/schema.sql` or `database/seed.sql` again into a populated database.
- Upload updated PHP/CSS/JS files to a **staging copy** with its own database; ensure `includes/config.php` remains private and unchanged.
- `upgrade-v2.sql` creates additional tables but does not delete existing rows.

## Important privacy / operational constraints

- Admin can see organization-wide operational events and manage users; Responder cannot access other users' profiles.
- Responder can create an incident, view own/assigned incident details, request/accept backup, and request equipment. Some operational backup information is shared among authenticated users.
- A handover only changes the new `incident_assignment` table after acknowledgment; the original incident's `reported_by` remains unchanged for historical accuracy.
- The application is **not a replacement for emergency dispatch**; network latency, hosting limits, browser suspension, and outages can delay updates.


## Completion patch (2026-11-21)
- Added database/upgrade-v2-completion.sql, required after upgrade-v2.sql.
- Added per-user operational notification center (read receipts, unread badge, polling) with visibility inherited from v2_events; max 200 recent events.
- Added Admin-managed emergency directory records and Responder visibility controls.
- These changes DO NOT make the full scope complete. Still outstanding: comprehensive equipment returns, fuel approval/receipts, full vehicle maintenance CRUD, and end-to-end MySQL testing.
