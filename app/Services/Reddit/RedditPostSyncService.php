<?php

namespace App\Services\Reddit;

use App\Models\RedditPost;
use App\Models\Subreddit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RedditPostSyncService
{
    /**
     * Update-safe columns. Qualification and comment state are intentionally
     * excluded so a later listing refresh cannot reset local processing state.
     *
     * @var list<string>
     */
    private const UPDATE_COLUMNS = [
        'title',
        'selftext',
        'author',
        'post_url',
        'permalink',
        'score',
        'num_comments',
        'reddit_created_at',
        'last_fetched_at',
        'is_self',
        'upvote_ratio',
        'raw_data',
    ];

    /**
     * Upsert posts from a Reddit listing page.
     *
     * @param  list<array<string, mixed>>  $children
     * @return array{fetched: int, inserted: int, updated: int, skipped: int, oldest: CarbonImmutable|null}
     */
    public function sync(Subreddit $subreddit, array $children, ?CarbonInterface $cutoff = null): array
    {
        $fetched = 0;
        $skipped = 0;
        $oldest = null;
        $rows = [];
        $fetchedAt = now()->format('Y-m-d H:i:s');

        foreach ($children as $child) {
            if (($child['kind'] ?? null) !== 't3' || ! is_array($child['data'] ?? null)) {
                continue;
            }

            $data = $child['data'];
            $redditId = trim((string) ($data['id'] ?? ''));
            $createdUtc = (int) ($data['created_utc'] ?? 0);
            $title = trim((string) ($data['title'] ?? ''));

            if ($redditId === '' || $createdUtc <= 0 || $title === '') {
                continue;
            }

            $createdAt = CarbonImmutable::createFromTimestampUTC($createdUtc)
                ->setTimezone((string) config('app.timezone'));

            $fetched++;

            if ($oldest === null || $createdAt->lt($oldest)) {
                $oldest = $createdAt;
            }

            if ($cutoff !== null && $createdAt->lt($cutoff)) {
                $skipped++;

                continue;
            }

            $upvoteRatio = null;

            if (isset($data['upvote_ratio']) && is_numeric($data['upvote_ratio'])) {
                $upvoteRatio = min(1, max(0, round((float) $data['upvote_ratio'], 4)));
            }

            $rows[$redditId] = [
                'subreddit_id' => $subreddit->getKey(),
                'reddit_id' => $redditId,
                'title' => $title,
                'selftext' => isset($data['selftext']) ? (string) $data['selftext'] : null,
                'author' => isset($data['author']) ? (string) $data['author'] : null,
                'post_url' => filled($data['url'] ?? null) ? (string) $data['url'] : null,
                'permalink' => filled($data['permalink'] ?? null) ? (string) $data['permalink'] : null,
                'score' => (int) ($data['score'] ?? 0),
                'num_comments' => (int) ($data['num_comments'] ?? 0),
                'reddit_created_at' => $createdAt->format('Y-m-d H:i:s'),
                'first_fetched_at' => $fetchedAt,
                'last_fetched_at' => $fetchedAt,
                'is_self' => (bool) ($data['is_self'] ?? false),
                'upvote_ratio' => $upvoteRatio,
                'raw_data' => $this->encodeJson($data),
            ];
        }

        if ($rows === []) {
            return [
                'fetched' => $fetched,
                'inserted' => 0,
                'updated' => 0,
                'skipped' => $skipped,
                'oldest' => $oldest,
            ];
        }

        $ids = array_keys($rows);
        $existing = RedditPost::query()
            ->whereIn('reddit_id', $ids)
            ->pluck('reddit_id')
            ->all();

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk(array_values($rows), 200) as $chunk) {
                RedditPost::query()->upsert($chunk, ['reddit_id'], self::UPDATE_COLUMNS);
            }
        });

        return [
            'fetched' => $fetched,
            'inserted' => count($ids) - count($existing),
            'updated' => count($existing),
            'skipped' => $skipped,
            'oldest' => $oldest,
        ];
    }

    private function encodeJson(array $data): ?string
    {
        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $encoded === false ? null : $encoded;
    }
}
