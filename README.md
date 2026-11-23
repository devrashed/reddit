# Reddit Business Lab

A Reddit-powered business idea and pain point discovery tool. The application includes the Phase 1
foundation, Phase 2 subreddit management, the Phase 3 Reddit collection engine, and the Phase 4
canonical post/comment data model.

## Stack

- PHP 8.2+
- Laravel 12
- MySQL 8+ / MariaDB (MySQL protocol compatible)
- Bootstrap 5, Bootstrap Icons, jQuery
- Blade, AJAX
- Database queue, Laravel Scheduler
- PHPUnit and Laravel Pint

Frontend assets are loaded from CDNs, so no Node build step is required.

## Requirements

- PHP 8.2 or newer with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- Composer 2
- MySQL 8+ or MariaDB 10.4+
- A running queue worker and a cron entry for `schedule:run`

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

Update the `DB_*` values and the Reddit collection settings in `.env`, then run:

```bash
php artisan migrate --seed
php artisan serve
```

Open <http://localhost:8000>.

Important queue settings:

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=900
```

`DB_QUEUE_RETRY_AFTER` must remain higher than the collection job timeout (`600` seconds).

Reddit request settings:

```env
REDDIT_BASE_URL=https://www.reddit.com
REDDIT_USER_AGENT="RedditBusinessLab/1.0 (Laravel; +http://localhost:8000)"
REDDIT_HTTP_TIMEOUT=10
REDDIT_RETRY_ATTEMPTS=3
REDDIT_REQUEST_DELAY_MS=1000
```

Use an accurate application name and contact URL in `REDDIT_USER_AGENT` in production.

## Scheduler and Queue

The scheduler must be invoked every minute:

```cron
* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
```

Run a persistent queue worker where possible:

```bash
php artisan queue:work --tries=3 --timeout=600
```

For shared hosting without a persistent worker, use a cron fallback with an overlap guard:

```cron
* * * * * cd /path-to-app && flock -n /tmp/reddit-queue.lock php artisan queue:work --stop-when-empty --tries=3 --timeout=600 >> /dev/null 2>&1
```

Restart queue workers after changing `.env` or deploying code.

## Reddit Collection

- New subreddits are queued for a one-time initial historical collection.
- Initial collection follows Reddit `data.after` pagination, filters posts to the configured historical
  window, and stops at the cutoff, end of listing, or a safety page cap.
- Initial progress and cursor are persisted so a failed attempt can resume safely.
- Regular collection runs at the configured times (default `08:00`, `14:00`, `20:00` in `Asia/Dhaka`).
- Regular collection updates metrics, rechecks qualification, selects qualified posts, and fetches
  accessible comments, including nested replies and bounded `more` expansion attempts.
- Per-subreddit cache locks prevent concurrent initial and regular collection.
- Collection limitations and failures are recorded in the database and visible under Collection Logs.
- No DeepSeek or AI analysis is called in this phase.

Recovery commands:

```bash
php artisan reddit:dispatch-initial-collection
php artisan reddit:dispatch-initial-collection --include-failed
php artisan reddit:dispatch-regular-collection --force
```

## Reddit Data Model

`reddit_posts` stores one row per Reddit post with a unique `reddit_id`:

- Content: `title`, `selftext`, `author`, `permalink`, `post_url`, `is_self`
- Metrics: `score`, `num_comments`, `upvote_ratio`
- Timestamps: `reddit_created_at`, `first_fetched_at`, `last_fetched_at`
- Processing state: `qualification_status`, `comments_collection_status`, `comments_collected_at`
- Raw Reddit payload in `raw_data`

`reddit_comments` stores one row per accessible comment:

- `post_id` foreign key, unique `reddit_comment_id`, `parent_id`, `author`, `body`, `score`
- `reddit_created_at`, `fetched_at`, plus optional `link_id` and `raw_data`

Indexes cover subreddit/creation lookups, score, comment counts, qualification status, comment post
lookups, and parent comment lookups. Post and comment syncs use upserts inside database transactions.
`first_fetched_at` is preserved on updates while `last_fetched_at` is refreshed. Missing content is not
deleted merely because it disappears from a later listing.

AI analysis state remains in `ai_analyses`; it is not stored on `reddit_posts` and is separate from
qualification and comment collection state.

## Administrator

The administrator account is provisioned by `AdminUserSeeder` from the `ADMIN_NAME`, `ADMIN_EMAIL`, and
`ADMIN_PASSWORD` environment values. No credentials are hardcoded in the application.

Applications that use XAMPP can serve the public directory at
`http://localhost/reddit/public` and set `APP_URL` accordingly.

## Useful Commands

```bash
php artisan migrate                 # Run pending migrations
php artisan migrate:fresh --seed    # Rebuild the local database
php artisan db:seed                 # Re-run the administrator seeder
php artisan schedule:list           # Inspect scheduled tasks
php artisan test                    # Run the automated test suite
./vendor/bin/pint                   # Format code with PSR-12 rules
php artisan route:list              # Inspect registered routes
```

## Application Structure

```
app/
  Console/Commands/          Reddit dispatch commands
  Enums/                     Collection and qualification states
  Http/
    Controllers/Admin/       Admin pages
    Requests/Admin/          Form validation
  Jobs/                      Initial and regular collection jobs
  Models/                    Eloquent models and relationships
  Services/Reddit/           HTTP client, collection, parsing, logging, settings
config/
  reddit.php                 Reddit endpoints, limits, defaults, qualification thresholds
  navigation.php             Sidebar navigation configuration
database/
  migrations/                Users, subreddits, posts, comments, logs, settings
  seeders/                   Administrator provisioning
public/
  css/admin.css              Admin styling
  js/admin.js                AJAX, CSRF, loader, modal, settings behavior
resources/views/             Admin pages, auth, components, errors
routes/
  web.php                    Authentication and admin routes
  console.php                Laravel scheduler registration
tests/                       Mocked-HTTP feature tests
```

## Implemented Scope

Phase 1: authentication, administrator access control, database schema, admin layout, navigation,
reusable UI components, and AJAX health status.

Phase 2: subreddit CRUD, search, filtering, pagination, activate/pause, AJAX deletion, duplicate
prevention, and generated Reddit JSON URLs.

Phase 3: public JSON collection engine, initial historical collection, scheduled regular collection,
post metric upserts, qualification rules, comment retrieval, collection logs, configurable collection
settings, queue jobs, scheduler, retries, and per-subreddit locks.

Phase 4: canonical `reddit_posts` and `reddit_comments` schemas, first/last fetch timestamps,
transactional upserts, relationship integrity, AI-analysis separation, indexing, and batch-safe data
access.

Deferred: Dashboard, DeepSeek integration, pain point extraction, business idea generation, and scoring.

## Collection Limitations

Reddit's public JSON endpoints may restrict access, rate-limit requests, omit older content, or return
`more` comment placeholders that cannot be expanded through the public endpoint. The collector does not
bypass blocks, CAPTCHA, authentication controls, or rate limits. Limitations are logged and historical
coverage is not guaranteed to be complete.
