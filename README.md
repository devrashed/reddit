# Reddit Business Lab

A Reddit-powered business idea and pain point discovery tool. This repository currently contains the
Phase 1 foundation: Laravel application scaffolding, database schema, administrator authentication, and a
responsive Bootstrap 5 admin workspace.

## Stack

- PHP 8.2+
- Laravel 12
- MySQL 8+ / MariaDB (MySQL protocol compatible)
- Bootstrap 5, Bootstrap Icons, jQuery
- Blade, AJAX
- PHPUnit and Laravel Pint

Frontend assets are loaded from CDNs, so no Node build step is required.

## Requirements

- PHP 8.2 or newer with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- Composer 2
- MySQL 8+ or MariaDB 10.4+

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the application database:

```sql
CREATE DATABASE reddit_ideas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Update the `DB_*` values in `.env`, then run:

```bash
php artisan migrate --seed
php artisan serve
```

Open <http://localhost:8000>.

## Administrator

The administrator account is provisioned by `AdminUserSeeder` from the `ADMIN_NAME`, `ADMIN_EMAIL`, and
`ADMIN_PASSWORD` environment values. No credentials are hardcoded in the application. Set those values in
`.env` before running the seeder.

Applications that use XAMPP can serve the public directory at
`http://localhost/reddit/public` and set `APP_URL` accordingly.

## Useful Commands

```bash
php artisan migrate                 # Run pending migrations
php artisan migrate:fresh --seed    # Rebuild the local database
php artisan db:seed                 # Re-run the administrator seeder
php artisan test                    # Run the automated test suite
./vendor/bin/pint                   # Format code with PSR-12 rules
php artisan route:list              # Inspect registered routes
```

## Application Structure

```
app/
  Http/
    Controllers/
      Admin/                 Admin page controllers
      Auth/                  Session authentication controller
    Middleware/              Admin access guard
    Requests/Auth/           Login validation and rate limiting
  Models/                    Eloquent models and relationships
  Services/                  Reusable application services
config/
  admin.php                  Administrator seeding configuration
  navigation.php             Sidebar navigation configuration
database/
  migrations/                Users, framework, and discovery domain tables
  seeders/                   Administrator provisioning
public/
  css/admin.css              Admin and authentication styling
  js/admin.js                jQuery, AJAX, CSRF, loader, and modal behavior
resources/views/
  admin/                     Admin pages
  auth/                      Login screen
  components/                Reusable Blade UI components
  errors/                    Custom error pages
routes/web.php               Authentication and admin routes
tests/                       Feature tests
```

## Phase 1 Scope

Implemented: authentication, administrator access control, database schema, admin layout, navigation,
reusable UI components, AJAX health status, and placeholder screens for the discovery modules.

Deferred to later phases: Reddit collection, DeepSeek integration, pain point extraction, business idea
generation, scoring, and settings management.
