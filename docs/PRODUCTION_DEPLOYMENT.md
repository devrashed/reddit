# Production and Shared-Hosting Deployment

## Production Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_TIMEZONE=Asia/Dhaka
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=30

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reddit_ideas
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=900

DEEPSEEK_API_KEY=
DEEPSEEK_BASE_URL=https://api.deepseek.com
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_TIMEOUT=60
DEEPSEEK_RETRY_ATTEMPTS=2
```

Use HTTPS, a strong `APP_KEY`, a strong administrator password, and a least-privilege database user. Never commit `.env`.

## Deployment Order

```bash
# stop/supervisor-quiesce queue workers first
php artisan down
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
php artisan queue:restart
```

Do not run `migrate:fresh` in production.

## Storage and Permissions

- Web server must serve the `public/` directory as document root.
- `storage/` and `bootstrap/cache/` must be writable by the PHP user.
- Keep `.env`, `storage/`, `vendor/`, and framework internals outside the public web root.

## Scheduler

```cron
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

## Queue Worker

Persistent worker:

```bash
php artisan queue:work --tries=3 --timeout=600
```

Run under Supervisor/systemd with restart and log rotation. On shared hosting without a persistent worker:

```cron
* * * * * cd /path-to-app && flock -n /tmp/reddit-queue.lock php artisan queue:work --stop-when-empty --tries=3 --timeout=600 >> /dev/null 2>&1
```

## Shared Hosting / cPanel

1. Select PHP 8.2+ and enable MySQL, mbstring, OpenSSL, cURL, XML, and tokenizer.
2. Upload the application outside `public_html` where possible.
3. Point the domain/subdomain document root to `public/`.
4. Create the MySQL database and user in cPanel.
5. Import/run migrations using Composer and Artisan via SSH or cPanel terminal.
6. Add the scheduler cron and, if no worker daemon is allowed, the queue cron fallback.
7. Set writable permissions for `storage/` and `bootstrap/cache/`.
8. For the included Mac development workaround, the database runs on port `3307`; production normally uses `3306`.

## Backups

- Daily database dumps, encrypted and retained off-server
- Regular `.env` backup in a secret manager
- Test restore procedures
- Backup before every migration/deployment

## Monitoring

Monitor:

- Application error log
- Failed jobs table
- Queue worker uptime
- Scheduler execution
- Collection Logs for repeated Reddit failures
- AI analysis failures and token usage
- Disk usage and database growth

## Security

- `APP_DEBUG=false`
- HTTPS-only
- Strong admin credentials
- API keys only in server environment
- CSRF, authentication, and admin middleware enabled
- Keep dependencies patched with `composer update` in a controlled window
- Restrict access to admin tooling and logs at the web-server level where practical

## Post-Deploy Verification

```bash
php artisan migrate:status
php artisan schedule:list
php artisan test
```

Verify login, subreddit listing, dashboard, saved AI report, queue processing, and scheduler.
