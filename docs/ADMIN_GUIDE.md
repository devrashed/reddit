# Admin User Guide

## Login

Use the administrator credentials configured through `ADMIN_EMAIL` and `ADMIN_PASSWORD` seeding values.

## Overview

The dashboard shows subreddit, post, qualification, comment, AI, pain point, business idea, and DeepSeek token totals with recent activity.

## Subreddits

Add, edit, activate, pause, search, filter, and delete communities. New communities queue a one-time historical collection. Deleting a subreddit also deletes its collected posts.

## Reddit Posts

- Search title, content, or author.
- Filter subreddit, qualification, AI status, date, score, and comments.
- Sort, paginate, and refresh.
- Open a post for full content, metrics, comments, and workflow status.

## AI Analysis

- **Analyze** creates the first report.
- **View Analysis** opens a saved report without an API call.
- **Re-analyze** requires confirmation and creates a new version.
- Processing and failed states are shown separately.
- The report page includes token usage, model, date, version history, and previous-version comparison.

## AI Analysis Section

Browse all saved analysis versions. Search by post title and filter by subreddit, status, or date. Completed versions link to their saved report.

## Pain Points

AI-generated pain points are linked to the source post and analysis version. Open details for root cause, severity, target audience, evidence, and related business ideas.

## Business Ideas

Open an idea for product type, target market, proposed solution, MVP features, monetization ideas, risks, evidence, and source links.

## Collection Logs

Review run type, timing, duration, posts, qualification counts, comments, errors, and status.

## Settings

Configure collection schedule, timezone, Reddit request behavior, historical window, DeepSeek model/timeout, and AI content limits. API secrets are not editable here.

## Important

AI findings are hypotheses. They do not prove demand, revenue, or willingness to pay. Validate externally.
