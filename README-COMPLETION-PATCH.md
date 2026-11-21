# e-TALA V2 — Expanded Integrated Development Package

This archive is based on the uploaded `e-TALA-clean-review.zip` via the previous V2 source, **not** a replacement for the separately deployed `/public/` application. **Do not deploy directly over the live InfinityFree site.**

## Install on a new staging database
1. Back up your code and database. Extract into a fresh XAMPP `htdocs/etala-v2-test` folder.
2. Create an empty MySQL database. Import `database/schema.sql`, then `database/seed.sql` once, followed by `database/upgrade-v2.sql`, `database/upgrade-v2-completion.sql`, and `database/upgrade-v2-final.sql`, **in that order**.
3. Copy `includes/config.example.php` to `includes/config.php` and configure DB host/name/user/password and `base_url` (`/etala-v2-test`).
4. Follow `README-V2.md` to create the initial Admin via the local CLI script. Log in with Admin and Responder test accounts.
5. Test all workflows and database constraints before any production migration. If upgrading an existing database, **never re-import schema.sql or seed.sql**; back up first and apply only the not-yet-applied upgrades.

## Newly implemented in this expansion
- Inventory stock-in/out adjustments with reasons and transaction history; returnable equipment partial/full return requests and Admin receipt verification; approved returns restock and update request status.
- Vehicle registration, status changes, vehicle assignments and returns, maintenance update details, fuel review and receipts (authenticated Admin-only download).
- CSV downloads for incidents, fuel, maintenance, equipment transactions and backup requests, with spreadsheet-formula injection mitigation.
- Responder Dashboard weather and role-filtered calendar; expanded weather forecast data.

## Existing integrated modules retained
- PHP/MySQL authentication and role checks, incident reporting, attachments and profiles; navy top navigation; operational activity feed; backup requests and acceptances; incident handovers; Admin-managed directory; notifications; Admin incident dashboard and analytics.

## Known limitations and mandatory validation
- **Not fully verified as a complete production application.** PHP lint and JS syntax checks do not prove the database migrations and browser workflows work. MySQL is unavailable in this build environment, so no full end-to-end DB test was performed.
- Vehicle status migration uses an ALTER TABLE; confirm live schema before applying.
- Vehicle assignment currently uses Admin assignment to an active user, not verified driving qualifications or approval by the assignee.
- Fuel entry is currently Admin-only; approval can be performed by an Admin, including the submitter. Independent two-person approval is not enforced.
- Maintenance updates do not yet support edit/delete of the original issue or automatic vehicle status transitions.
- Calendar is role-filtered and linked to incidents but does not yet offer category/location filters and dedicated day detail panels.
- Weather search prefers a Philippine exact city match but does not offer a full city-disambiguation autocomplete.
- The legacy incident CRUD audit trail and all cross-role acceptance scenarios require testing and potentially more integration work.
- Do not treat the web app as the sole emergency dispatch/notification channel.
