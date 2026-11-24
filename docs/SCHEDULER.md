# Scheduler Setup

The scheduler checks configured collection windows every minute.

## Cron

```cron
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

Verify:

```bash
php artisan schedule:list
```

## Collection Times

Configure under **Admin → Settings**:

- Daily frequency
- Collection times
- Collection timezone
- Enable/disable scheduled collection

Default: `08:00`, `14:00`, `20:00` in `Asia/Dhaka`.

The dispatch command only queues jobs inside the configured due window. A cache slot prevents duplicate dispatch for the same time window.

## Manual Commands

```bash
php artisan reddit:dispatch-regular-collection --force
php artisan reddit:dispatch-initial-collection
php artisan reddit:dispatch-initial-collection --include-failed
```

## Shared Hosting

cPanel cron supports `schedule:run` every minute. Confirm PHP CLI uses the correct binary and the application path is absolute.

The scheduler dispatches jobs; a queue worker is separately required.
