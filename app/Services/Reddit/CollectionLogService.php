<?php

namespace App\Services\Reddit;

use App\Models\CollectionLog;
use App\Models\Subreddit;
use Illuminate\Support\Str;
use Throwable;

class CollectionLogService
{
    public function start(Subreddit $subreddit, string $type): CollectionLog
    {
        return CollectionLog::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'collection_type' => $type,
            'status' => CollectionLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array{fetched?: int, created?: int, updated?: int, qualified?: int, pending_checked?: int, newly_qualified?: int, comments?: int, failed?: int}  $counts
     * @param  list<string>  $notes
     */
    public function complete(
        CollectionLog $log,
        array $counts,
        array $notes = [],
        ?string $warning = null
    ): CollectionLog {
        $log->forceFill([
            'status' => CollectionLog::STATUS_COMPLETED,
            'finished_at' => now(),
            'posts_fetched' => (int) ($counts['fetched'] ?? 0),
            'posts_created' => (int) ($counts['created'] ?? 0),
            'posts_updated' => (int) ($counts['updated'] ?? 0),
            'qualified_posts' => (int) ($counts['qualified'] ?? $counts['newly_qualified'] ?? 0),
            'pending_posts_checked' => (int) ($counts['pending_checked'] ?? 0),
            'newly_qualified_posts' => (int) ($counts['newly_qualified'] ?? 0),
            'comments_fetched' => (int) ($counts['comments'] ?? 0),
            'failed_operations' => (int) ($counts['failed'] ?? 0),
            'notes' => $this->formatNotes($notes),
            'error_message' => $warning === null ? null : Str::limit($warning, 2000),
        ])->save();

        return $log;
    }

    /**
     * @param  array{fetched?: int, created?: int, updated?: int, qualified?: int, pending_checked?: int, newly_qualified?: int, comments?: int, failed?: int}  $counts
     * @param  list<string>  $notes
     */
    public function fail(
        CollectionLog $log,
        Throwable $exception,
        array $counts = [],
        array $notes = []
    ): CollectionLog {
        $log->forceFill([
            'status' => CollectionLog::STATUS_FAILED,
            'finished_at' => now(),
            'posts_fetched' => (int) ($counts['fetched'] ?? 0),
            'posts_created' => (int) ($counts['created'] ?? 0),
            'posts_updated' => (int) ($counts['updated'] ?? 0),
            'qualified_posts' => (int) ($counts['qualified'] ?? $counts['newly_qualified'] ?? 0),
            'pending_posts_checked' => (int) ($counts['pending_checked'] ?? 0),
            'newly_qualified_posts' => (int) ($counts['newly_qualified'] ?? 0),
            'comments_fetched' => (int) ($counts['comments'] ?? 0),
            'failed_operations' => max(1, (int) ($counts['failed'] ?? 1)),
            'error_message' => Str::limit($exception->getMessage(), 2000),
            'notes' => $this->formatNotes($notes),
        ])->save();

        return $log;
    }

    /**
     * @param  list<string>  $notes
     */
    private function formatNotes(array $notes): ?string
    {
        $notes = array_values(array_unique(array_filter(
            array_map(static fn (mixed $note): string => trim((string) $note), $notes),
            static fn (string $note): bool => $note !== ''
        )));

        if ($notes === []) {
            return null;
        }

        return Str::limit(implode("\n", $notes), 5000);
    }
}
