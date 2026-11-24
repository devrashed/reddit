# Queue Setup

Queue driver: `database`.

## Requirements

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=900
```

`DB_QUEUE_RETRY_AFTER` must be greater than the collection job timeout (`600` seconds).

## Persistent Worker

```bash
php artisan queue:work --tries=3 --timeout=600
```

Restart workers after deployment or environment changes:

```bash
php artisan queue:restart
```

## Supervisor (VPS)

Run one or more workers under Supervisor/systemd, with automatic restart and log rotation. Set the working directory to the application root.

## Shared Hosting Fallback

```cron
* * * * * cd /path-to-app && flock -n /tmp/reddit-queue.lock php artisan queue:work --stop-when-empty --tries=3 --timeout=600 >> /dev/null 2>&1
```

`flock` prevents overlapping cron workers where available. Per-subreddit and per-post locks provide additional protection.

## Without a Worker

- Initial collections remain queued/pending.
- Regular collections remain queued.
- Qualified posts wait for comment jobs.
- Nothing is lost, but no background progress occurs.
