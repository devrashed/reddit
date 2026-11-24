# Installation Guide

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`
- Composer 2
- MySQL 8+ or MariaDB 10.4+
- Web server or Laravel's development server
- No Node build step is required

## Install

```bash
cd /path/to/reddit
composer install
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`, create the database, then run:

```bash
php artisan migrate --seed
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` before seeding the administrator account.

## Run Locally

```bash
php artisan serve
```

Open `http://127.0.0.1:8000`.

## XAMPP

1. Place the project under `htdocs`.
2. Point the browser at `http://localhost/reddit/public`.
3. Set `APP_URL` to the matching URL.
4. Start MySQL/MariaDB and create `reddit_ideas`.

## Verify

```bash
php artisan migrate:status
php artisan test
php artisan schedule:list
```

The application is ready when login works, `/admin` loads, and the test suite passes.
