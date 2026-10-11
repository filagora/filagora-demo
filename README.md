# ForumKit Demo

A clean Laravel 13 and Filament 5 application that installs the ForumKit packages. Use it
to try them, to develop them side by side, and as a worked example of wiring them into
your own app.

> The packages are at `0.1`: the API may change between `0.x` minor versions.

| Package (Composer) | Source (GitHub) | What it adds |
| --- | --- | --- |
| `zencart/forumkit-core` | [zcwilt/forumkit-core](https://github.com/zcwilt/forumkit-core) | Module and navigation registries, table prefix, user model resolution, system settings, markdown, `forumkit:doctor`, `forumkit:install`, `forumkit:adopt` |
| `zencart/forumkit-entitlements` | [zcwilt/forumkit-entitlements](https://github.com/zcwilt/forumkit-entitlements) | Entitlement definitions, grants, default grants, requests, the resolver and authorization service, and the staff screens |

`zencart` is the Composer vendor name the packages are published under. The repositories
live under `zcwilt` on GitHub for now.

## Run it locally (DDEV)

```bash
ddev start
ddev composer install
cp .env.example .env
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
```

Open <https://forumkit-demo.ddev.site/admin> and sign in:

| User | Email | Password | Can do |
| --- | --- | --- | --- |
| Staff | `admin@example.test` | `password` | Everything (holds `forumkit.*`) |
| Member | `member@example.test` | `password` | Open the dashboard, nothing else |

The seeded member has one pending entitlement request. Approve it from
**Queues → Entitlement requests**, then check **Members → Users → Demo Member** to see the
grant it created.

These passwords are public. This is a development app, never deploy it.

## How the packages are wired in

1. **Install:** `composer require zencart/forumkit-core zencart/forumkit-entitlements`,
   then `php artisan forumkit:install` (publishes config, migrates, runs the doctor).
2. **Register the plugins** on the panel
   ([`AdminPanelProvider`](app/Providers/Filament/AdminPanelProvider.php)):

   ```php
   ->plugins([
       ForumKitCorePlugin::make(),
       ForumKitEntitlementsPlugin::make(),
   ])
   ```

3. **Add the trait** to your user model
   ([`User`](app/Models/User.php)): `use HasEntitlements;`. ForumKit never alters your
   `users` table and ships no Users screen.
4. **Attach the relation managers** to your own user resource
   ([`UserResource`](app/Filament/Resources/Users/UserResource.php)):
   `UserEntitlementsRelationManager` and `UserEntitlementRequestsRelationManager`.
5. **Grant access.** Every screen is denied until a user holds the matching entitlement,
   through a Gate ability each package maps to an entitlement key
   (`forumkit-entitlements.gates`). The [seeder](database/seeders/DemoSeeder.php) gives
   the staff user one wildcard grant, `forumkit.*`.

## Develop the packages alongside this app

Clone the packages next to this project:

```bash
git clone https://github.com/zcwilt/forumkit-core.git
git clone https://github.com/zcwilt/forumkit-entitlements.git
```

```
forumkit/
  forumkit-core/
  forumkit-entitlements/
  forumkit-demo/        <- this
```

then:

```bash
bin/dev-link     # vendor/zencart/* now symlinks to your checkouts
bin/dev-unlink   # back to the committed composer.json and composer.lock
```

`bin/dev-link` edits `composer.json` and `composer.lock`. Do not commit them in that
state: run `bin/dev-unlink` first.

## Checks

```bash
ddev exec vendor/bin/pest        # runs against MariaDB, database "testing"
ddev exec vendor/bin/pint
ddev exec php artisan forumkit:doctor
ddev exec php artisan forumkit:adopt --dry-run   # for a database that already holds the tables
```

CI runs the same on MariaDB 11.4, plus a leak scan.

## Licence

MIT. See [LICENSE](LICENSE).
