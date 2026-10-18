# AGENTS

Guidance for AI coding agents working in `filagora-demo`. `CLAUDE.md` imports this file; edit here.

## What this is

A clean Laravel 13 / Filament 5 application that installs every Filagora package: for trying them, for developing them side by side, and as the worked example of wiring them into an app. It has seeded data and smoke tests. **It is a development app and is never deployed**; its seeded passwords are public. Part of Filagora, a family of MIT-licensed Laravel 13 / Filament 5 packages (forums, CMS, entitlements, logs, compliance, messages, search, push, memberships). Composer vendor `filagora`, namespace `Filagora\<Name>\`, default table prefix `filagora_`, commands `filagora:*`, env `FILAGORA_*`. Every package is pre-1.0 (`0.x`): the API may change between minor versions.

## How it differs from the packages

- It is an app, not a library: `app/`, `routes/`, `bootstrap/`, `artisan`, `.env.example`. Packages come from Composer by version constraint (`composer.json`), so a package change reaches the demo only after it is tagged and the constraint is bumped, unless you are deliberately testing with a local path repository (do not commit that).
- Panel wiring lives in `app/Providers/Filament/AdminPanelProvider.php` (every package's `...Plugin::make()`); host-side bindings (`AccessChecker` or entitlements, user model traits) live in `app/`.
- Seeders populate every package so the README tour works (staff and member users, a moderated thread with a report, a poll, a private message, plans and billing events, pages). When a package adds a feature, extend the seed and the README tour in the same change.
- Smoke tests in `tests/` install-check each package. They are the proof that packages compose; run them after bumping any package.

## Commands

```bash
ddev start && ddev composer install
cp .env.example .env && ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
ddev exec php artisan test
ddev exec vendor/bin/pint
```

Open `https://filagora-demo.ddev.site/admin`; staff `admin@example.test` / `password`.

## Rules

- Use only `example.test`-style addresses and public data: this repo is public and CI leak-scans it.
- README and `.env.example` are part of done.
- Branch + PR, squash merge. Confirm with the user before pushing tags or doing anything outward-facing.
- When a package's `filagora:*` command or config changes, update the "How the packages are wired in" section of the README.
