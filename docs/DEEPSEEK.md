# DeepSeek API Setup

## Credentials

Set the key only in the server environment:

```env
DEEPSEEK_API_KEY=your_real_key
DEEPSEEK_BASE_URL=https://api.deepseek.com
DEEPSEEK_MODEL=deepseek-chat
DEEPSEEK_TIMEOUT=60
DEEPSEEK_RETRY_ATTEMPTS=2
```

Then:

```bash
php artisan config:clear
```

The key is never stored in the settings table, sent to JavaScript, or included in HTML.

## Admin Settings

Configure under **Admin → Settings**:

- DeepSeek model
- DeepSeek timeout
- Number of saved comments sent
- Evidence character limit

These are non-secret settings. API credentials remain in `.env`.

## Manual Workflow

AI analysis is never automatic.

1. Admin opens a Reddit post and clicks **Analyze**.
2. The post and saved MySQL comments are packaged.
3. DeepSeek returns strict JSON.
4. The report, model, token usage, status, and version are saved.
5. Pain point and business idea records are created transactionally.
6. The admin is redirected to the saved report.

Saved reports open without another API call. **Re-analyze** requires confirmation and creates a new version.

## Safety Rules

The prompt instructs the model not to invent market size, revenue, demand, or willingness to pay. A single Reddit post is not proof of demand. Evidence is separated into supported facts, AI interpretations, and hypotheses.

## Errors

- Missing key: configuration error, no external call
- 401: credentials rejected
- 402: insufficient credits
- 429: bounded retries, then safe error
- Timeout/network/5xx: bounded retries
- Invalid/empty/incomplete JSON: failed analysis record

Failed analyses can be intentionally retried. Concurrent analysis for one post is blocked by cache lock, status checks, and a version unique constraint.

## Cost Control

- Only one post and bounded saved comments are sent.
- Saved reports are reused.
- Re-analysis is explicit.
- Comment count and content limits are configurable.
- Token usage is stored when returned; missing values remain null.
