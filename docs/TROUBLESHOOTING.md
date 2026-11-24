# Troubleshooting

## Database Connection Refused

Start MySQL/MariaDB and verify the host, port, database, and credentials in `.env`.

```bash
php artisan migrate:status
```

On this Mac development setup, the isolated MariaDB service uses port `3307`.

## 500 Error After Database Outage

Clear stale sessions if needed and verify the database is reachable. The application requires database-backed sessions.

## Reddit 403

Reddit may block the public endpoint or User-Agent. Update `REDDIT_USER_AGENT`; do not attempt CAPTCHA or access-control bypasses. Limits are logged.

## Reddit 429

The collector retries with backoff. Increase request delay or reduce collection volume. Repeated failures are recorded in Collection Logs.

## Collection Not Running

Check all of:

```bash
php artisan schedule:list
php artisan queue:work --tries=3 --timeout=600
```

Also verify the subreddit is active and initial collection is completed. For a pre-existing pending source:

```bash
php artisan reddit:dispatch-initial-collection
```

## Jobs Queued but Not Processed

Start a queue worker. On shared hosting, configure the cron fallback from the Queue guide.

## AI Button Shows Configuration Error

Set `DEEPSEEK_API_KEY` in `.env` and run `php artisan config:clear`.

## AI Request Fails

Check Collection/AI errors for:

- Invalid credentials (401)
- Insufficient credits (402)
- Rate limits (429)
- Timeout or provider outage
- Invalid or incomplete model JSON

Failed versions can be retried intentionally.

## Analysis Stuck in Processing

Wait for timeout/stale recovery, then retry. Locks and duplicate protection prevent concurrent calls.

## No Pain Point or Idea Appears

Insights are created only for successfully completed analyses. Failed or incomplete reports do not create insight records.

## Permission Errors

Ensure `storage/` and `bootstrap/cache/` are writable by the web/PHP user.

## Assets Unavailable

Bootstrap, Bootstrap Icons, and jQuery are CDN-hosted. Verify internet access or vendor them locally.

## Debugging

Set `APP_DEBUG=true` only locally. Never expose debug pages or credentials in production.
