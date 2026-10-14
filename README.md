# Filagora Demo

A clean Laravel 13 and Filament 5 application that installs the Filagora packages. Use it
to try them, to develop them side by side, and as a worked example of wiring them into
your own app.

> The packages are at `0.2`: the API may change between `0.x` minor versions.

| Package | What it adds |
| --- | --- |
| [`filagora/filagora-core`](https://github.com/filagora/filagora-core) | Module and navigation registries, table prefix, user model resolution, system settings, markdown, `filagora:doctor`, `filagora:install`, `filagora:adopt` |
| [`filagora/filagora-entitlements`](https://github.com/filagora/filagora-entitlements) | Entitlement definitions, grants, default grants, requests, the resolver and authorization service, and the staff screens |
| [`filagora/filagora-cms`](https://github.com/filagora/filagora-cms) | Pages, menus, reusable blocks and media, with drafts, versions, scheduling and preview |
| [`filagora/filagora-compliance`](https://github.com/filagora/filagora-compliance) | Cookie consent, cookie and privacy policy versions, and privacy requests with export, erasure and retention |
| [`filagora/filagora-logs`](https://github.com/filagora/filagora-logs) | The email log: every outgoing message, when logging is switched on |
| [`filagora/filagora-forums`](https://github.com/filagora/filagora-forums) | Forum groups, forums, threads and posts with visibility and posting rules, moderation, reports, and the staff screens and moderation queue (no public frontend) |

## Run it locally (DDEV)

```bash
ddev start
ddev composer install
cp .env.example .env
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
```

Open <https://filagora-demo.ddev.site/admin> and sign in:

| User | Email | Password | Can do |
| --- | --- | --- | --- |
| Staff | `admin@example.test` | `password` | Everything (holds `filagora.*`) |
| Member | `member@example.test` | `password` | Open the dashboard, nothing else |

The seeded member has one pending entitlement request. Approve it from
**Queues → Entitlement requests**, then check **Members → Users → Demo Member** to see the
grant it created.

The seeded forum has a thread waiting for approval and a reported reply: open **Queues →
Moderation queue** to approve the thread and resolve the report, or browse **Forum →
Forums, Threads, Posts**. The dashboard shows how much is waiting. **Logs → Email log** shows a logged message, and
anything the demo sends from now on (logging is switched on in the seed). **Privacy and compliance → Privacy requests** has the member's data export request: open it and press *Generate export* (the file is written under `storage/app/private/exports/privacy`). **Forum → Announcements** has a published banner for signed-in users (the package ships the screen and the rules, not the page markup). **Content → Pages** has a published
"Welcome" page with a hero block and a draft "About" page: edit a block, save the draft, then publish.

These passwords are public. This is a development app, never deploy it.

## How the packages are wired in

1. **Install:** `composer require filagora/filagora-core filagora/filagora-entitlements filagora/filagora-forums filagora/filagora-logs filagora/filagora-cms filagora/filagora-compliance`,
   then `php artisan filagora:install` (publishes config, migrates, runs the doctor) and
   `php artisan filagora:entitlements:sync`.
2. **Register the plugins** on the panel
   ([`AdminPanelProvider`](app/Providers/Filament/AdminPanelProvider.php)):

   ```php
   ->plugins([
       FilagoraCorePlugin::make(),
       FilagoraEntitlementsPlugin::make(),
       FilagoraForumsPlugin::make(),
       FilagoraLogsPlugin::make(),
       FilagoraCmsPlugin::make(),
       FilagoraCompliancePlugin::make(),
   ])
   ```

3. **Add the trait** to your user model
   ([`User`](app/Models/User.php)): `use HasEntitlements;`. Filagora never alters your
   `users` table and ships no Users screen.
4. **Attach the relation managers** to your own user resource
   ([`UserResource`](app/Filament/Resources/Users/UserResource.php)):
   `UserEntitlementsRelationManager` and `UserEntitlementRequestsRelationManager`.
5. **Grant access.** Every screen is denied until a user holds the matching entitlement,
   through a Gate ability each package maps to an entitlement key
   (`filagora-entitlements.gates`). The [seeder](database/seeders/DemoSeeder.php) gives
   the staff user one wildcard grant, `filagora.*`.

## Develop the packages alongside this app

Clone the packages next to this project:

```bash
git clone https://github.com/filagora/filagora-core.git
git clone https://github.com/filagora/filagora-entitlements.git
git clone https://github.com/filagora/filagora-forums.git
git clone https://github.com/filagora/filagora-logs.git
git clone https://github.com/filagora/filagora-cms.git
git clone https://github.com/filagora/filagora-compliance.git
```

```
filagora/
  filagora-core/
  filagora-entitlements/
  filagora-forums/
  filagora-logs/
  filagora-cms/
  filagora-compliance/
  filagora-demo/        <- this
```

then:

```bash
bin/dev-link     # vendor/filagora/* now symlinks to your checkouts
bin/dev-unlink   # back to the committed composer.json and composer.lock
```

`bin/dev-link` edits `composer.json` and `composer.lock`. Do not commit them in that
state: run `bin/dev-unlink` first.

## Checks

```bash
ddev exec vendor/bin/pest        # runs against MariaDB, database "testing"
ddev exec vendor/bin/pint
ddev exec php artisan filagora:doctor
ddev exec php artisan filagora:adopt --dry-run   # for a database that already holds the tables
```

CI runs the same on MariaDB 11.4, plus a leak scan.

## Licence

MIT. See [LICENSE](LICENSE).
