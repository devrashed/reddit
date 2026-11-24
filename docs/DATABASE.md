# Database Setup Guide

## Create Database

```sql
CREATE DATABASE reddit_ideas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Create a separate test database only if tests are configured to use MySQL. The default test suite uses in-memory SQLite.

## Migrations

```bash
php artisan migrate
php artisan migrate --seed
php artisan migrate:fresh --seed   # local/reset only
```

Major schema areas:

- Users, sessions, cache, and jobs
- Subreddits and collection progress
- Reddit posts and comments
- Collection logs
- AI analysis versions, tokens, and statuses
- AI-generated pain points and business ideas
- Application settings

## Seeding

`DatabaseSeeder` provisions only the administrator from `ADMIN_*` environment values. No example subreddits or production sample data are seeded.

## Integrity

- Unique Reddit post, comment, subreddit, and analysis-version identifiers
- Foreign keys with cascade/null-on-delete behavior
- Transactional upserts and transactional AI insight persistence
- Indexes for collection, qualification, comments, reporting, and AI history

## Rollback

```bash
php artisan migrate:rollback --step=1
```

Test migrations on a scratch database before production changes.


## Core Tables

- `users`, `sessions`, `password_reset_tokens`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`
- `subreddits`
- `reddit_posts`
- `reddit_comments`
- `collection_logs`
- `ai_analyses`
- `pain_points`
- `business_ideas`
- `settings`

## Key Relationships

- Subreddit has many Reddit posts and collection logs.
- Reddit post belongs to a subreddit, has many comments and AI analysis versions, and has many source-linked pain points and business ideas.
- Reddit comment belongs to a post; parent IDs preserve threaded structure.
- AI analysis version belongs to a post and has at most one generated pain point and one generated business idea.
- Pain point belongs to a source post and analysis version, and has many business ideas.
- Business idea belongs to a pain point, source post, and analysis version.

## Important Constraints

- Unique Reddit post ID and Reddit comment ID.
- Unique AI analysis `(post_id, version)`.
- Unique `ai_analysis_id` for generated pain points and business ideas.
- Foreign keys cascade/null where appropriate to avoid orphaned records.
- Token fields are nullable when the provider does not return usage.
## Backups

Back up the database and `.env` securely before deploying migrations. Restore drills should be part of routine operations.
