# Reddit Collection Guide

## Sources

The collector uses public Reddit JSON endpoints only. It does not bypass authentication, CAPTCHA, blocks, or rate limits.

Set a descriptive server-side User-Agent in `.env`.

## Initial Collection

A new subreddit queues a one-time historical collection.

- Default window: two months
- Pagination: Reddit `data.after` cursor
- Stops at cutoff, listing end, or safety page cap
- Saves cursor, pages, and fetched counts
- Completed collections are not restarted
- Failed attempts can resume from saved progress

Recovery:

```bash
php artisan reddit:dispatch-initial-collection
php artisan reddit:dispatch-initial-collection --include-failed
```

## Regular Collection

Default schedule: three times daily, configurable in Settings.

Each run:

1. Fetches recent listing pages.
2. Upserts posts and metric updates.
3. Qualifies posts with **score >= 5 AND num_comments >= 5**.
4. Rechecks a bounded batch of older pending posts through Reddit's public info endpoint.
5. Dispatches comment jobs for qualified posts that need comments.

## Qualification

Low-engagement posts remain stored as `pending`. They are rechecked when metrics change. Both thresholds are mandatory.

## Comments

Nested comments are stored flat with parent references and rendered as a threaded tree. `more` placeholders are expanded within configured limits. Inaccessible comments are logged rather than invented.

## Logs

Collection Logs display start/end time, duration, posts fetched/updated, pending checks, newly qualified posts, comments, failures, and status.

## Limitations

Public listings may omit older content, rate-limit requests, or require access that is not publicly available. Historical coverage is not guaranteed. All limitations are logged.
