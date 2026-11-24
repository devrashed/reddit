# Environment Configuration

Copy `.env.example` to `.env`; never commit `.env` or real credentials.

| Variable | Purpose |
|---|---|
| `APP_NAME` | Application name |
| `APP_ENV` | `local`, `staging`, or `production` |
| `APP_DEBUG` | `true` locally, `false` in production |
| `APP_URL` | Public application URL |
| `APP_TIMEZONE` | Application timezone; default `Asia/Dhaka` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT` | Database host and port |
| `DB_DATABASE` | `reddit_ideas` |
| `DB_USERNAME`, `DB_PASSWORD` | Database credentials |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `DB_QUEUE_RETRY_AFTER` | Must exceed collection job timeout; default `900` |
| `LOG_CHANNEL`, `LOG_STACK` | Error logging channels |
| `REDDIT_BASE_URL` | Public Reddit base URL |
| `REDDIT_USER_AGENT` | Descriptive collector identity |
| `REDDIT_HTTP_TIMEOUT` | Reddit request timeout |
| `REDDIT_RETRY_ATTEMPTS` | Reddit retry attempts |
| `REDDIT_REQUEST_DELAY_MS` | Delay between Reddit requests |
| `DEEPSEEK_API_KEY` | Server-only DeepSeek credential |
| `DEEPSEEK_BASE_URL` | DeepSeek API base URL |
| `DEEPSEEK_MODEL` | Default model fallback |
| `DEEPSEEK_TIMEOUT` | DeepSeek request timeout |
| `DEEPSEEK_RETRY_ATTEMPTS` | DeepSeek retry attempts |

Administrator bootstrap values:

```env
ADMIN_NAME="Administrator"
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

After changing environment values:

```bash
php artisan config:clear
```

Do not place `DEEPSEEK_API_KEY` in the settings database, JavaScript, HTML, documentation examples, or source control.
