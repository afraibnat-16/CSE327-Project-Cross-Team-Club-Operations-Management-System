# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# CAPTrack

NSU ACM CAP Force sponsor-tracking system. Course project, CSE327.

## Stack

Laravel 12 (PHP 8.3), Livewire + Volt + Flux UI, Blade, Tailwind v4 (Vite), MySQL (SQLite while developing), PHPUnit. Built on the official Laravel Livewire starter kit. Work follows day-by-day guides in `docs/dayN.md`.

## Hard rules

- Design patterns are written FROM SCRATCH in `app/Patterns/`. Do NOT use Laravel Events/Listeners, Policies, `app()->singleton()`, or Laravel "Facades" to implement a pattern.
- Patterns: Adapter, Singleton, Builder, Factory Method, Proxy, Composite, Facade, Observer. Each has its own folder (`app/Patterns/Factory/` is for Factory Method). Namespace: `App\Patterns\<Folder>`.
- Keep code simple and commented in plain language. A student must be able to explain every line.
- Every pattern class gets a PHPUnit test in `tests/Unit/Patterns/`.
- Never edit `.env` or commit secrets.

## Domain

- Sponsor stages: Negotiation > Approval > Logistics > Ready > On Campus > Completed (or Cancelled).
- Teams: Corporate, Admin, Provision, plus Coordinator.
- Permissions are only LOGGED in the app.

## Commands

```bash
php artisan serve                     # run app
composer dev                          # serve + queue + log tail (pail) + vite together
npm run dev / npm run build           # Vite dev server / build assets (views need one of these)

php artisan test                      # all tests
php artisan test --testsuite=Unit
php artisan test tests/Unit/Patterns/SomeTest.php      # one file
php artisan test --filter=test_method_name             # one test

php artisan migrate:fresh --seed      # fresh database
vendor/bin/pint                       # code style (CI runs this)
```

- Tests use in-memory SQLite and array cache/session/mail drivers (see `phpunit.xml`).
- CI (`.github/workflows/`) runs Pint and PHPUnit on pushes and PRs to `main` and `develop`.

## Architecture

- **UI pages are Volt single-file components.** The PHP class and Blade template live together in `resources/views/livewire/**.blade.php`. They are routed with `Volt::route('settings/profile', 'settings.profile')` in `routes/web.php` and `routes/auth.php`. New pages should follow this pattern rather than adding controllers.
- **Layouts** are Blade components in `resources/views/components/layouts/`. `app` uses the sidebar/header shells. `auth` uses the simple/card/split variants. UI primitives are Flux (`<flux:...>`), and local Flux overrides go in `resources/views/flux/`.
- `docs/diagrams` and `docs/report` hold course deliverables (UML, report).

## Gotchas

- **Write tests as PHPUnit classes, not Pest.** `tests/Pest.php` exists, but Pest is not installed. Write each test as a class extending `Tests\TestCase`, and add `RefreshDatabase` when it touches the DB. Unit tests for pure pattern classes can extend `PHPUnit\Framework\TestCase`.
- **`bootstrap/providers.php` registers a class that doesn't exist.** It names `App\Providers\FolioServiceProvider`, but that class is missing and Folio isn't installed.
- **Empty folders aren't in git.** A pattern or docs folder only reaches GitHub once it holds a file (or a `.gitkeep`).
- **Livewire is v4**, not v3. `config/livewire.php` points full-page components at `components.layouts.app`; keep that, or the Settings pages break.
