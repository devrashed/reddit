<?php

namespace App\Services\Reddit;

use App\Enums\CommentCollectionStatus;
use App\Models\RedditComment;
use App\Models\RedditPost;
use Illuminate\Support\Facades\DB;
use Throwable;

class CommentCollectionService
{
    public function __construct(
        private readonly RedditApiClient $client,
        private readonly RedditCommentParser $parser
    ) {}

    /**
     * Fetch and upsert accessible comments for a qualified post.
     *
     * @return array{comments_fetched: int, notes: list<string>}
     */
    public function collect(RedditPost $post): array
    {
        $post->forceFill([
            'comments_collection_status' => CommentCollectionStatus::Running->value,
            'comment_collection_attempts' => $post->comment_collection_attempts + 1,
        ])->save();

        try {
            $maxDepth = max(1, (int) config('reddit.comments_max_depth', 20));
            $parsed = $this->parser->parseCommentsResponse(
                $this->client->fetchPostComments($post),
                $maxDepth
            );

            $comments = [];

            foreach ($parsed['comments'] as $comment) {
                $comments[$comment['reddit_comment_id']] = $comment;
            }

            $notes = $parsed['notes'];
            $moreIds = $parsed['more_ids'];
            $morePlaceholders = $parsed['more_placeholders'];
            $moreCount = $parsed['more_count'];
            $processedMoreIds = [];
            $batches = 0;

            $maxBatches = max(0, (int) config('reddit.comments_more_batches', 2));
            $batchSize = max(1, (int) config('reddit.comments_more_batch_size', 100));

            while ($moreIds !== [] && $batches < $maxBatches) {
                $batch = array_values(array_diff($moreIds, $processedMoreIds));

                if ($batch === []) {
                    break;
                }

                $batch = array_slice($batch, 0, $batchSize);
                $processedMoreIds = array_values(array_unique(array_merge($processedMoreIds, $batch)));

                try {
                    $moreParsed = $this->parser->parseMoreResponse(
                        $this->client->fetchMoreComments($post, $batch),
                        $maxDepth
                    );
                } catch (Throwable $exception) {
                    $notes[] = 'Some additional comments could not be expanded: '.$exception->getMessage();

                    break;
                }

                foreach ($moreParsed['comments'] as $comment) {
                    $comments[$comment['reddit_comment_id']] = $comment;
                }

                $moreIds = array_values(array_unique(array_merge($moreIds, $moreParsed['more_ids'])));
                $morePlaceholders += $moreParsed['more_placeholders'];
                $moreCount += $moreParsed['more_count'];
                $notes = array_merge($notes, $moreParsed['notes']);
                $batches++;
            }

            if ($morePlaceholders > 0) {
                $notes[] = sprintf(
                    '%d more-comment placeholder(s) representing at least %d comment(s) were returned; %d additional request(s) processed.',
                    $morePlaceholders,
                    $moreCount,
                    $batches
                );

                if (array_diff($moreIds, $processedMoreIds) !== []) {
                    $notes[] = 'Some additional comments were not accessible through the public JSON endpoint.';
                }
            }

            $fetched = $this->upsertComments($post, array_values($comments));

            $post->forceFill([
                'comments_collection_status' => CommentCollectionStatus::Completed->value,
                'comments_collected_at' => now(),
            ])->save();

            return [
                'comments_fetched' => $fetched,
                'notes' => array_values(array_unique($notes)),
            ];
        } catch (Throwable $exception) {
            $post->forceFill([
                'comments_collection_status' => CommentCollectionStatus::Failed->value,
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $comments
     */
    private function upsertComments(RedditPost $post, array $comments): int
    {
        if ($comments === []) {
            return 0;
        }

        $fetchedAt = now()->format('Y-m-d H:i:s');
        $rows = [];

        foreach ($comments as $comment) {
            $rows[] = [
                'post_id' => $post->getKey(),
                'reddit_comment_id' => $comment['reddit_comment_id'],
                'parent_id' => $comment['parent_id'],
                'author' => $comment['author'],
                'body' => $comment['body'],
                'score' => (int) $comment['score'],
                'link_id' => $comment['link_id'],
                'reddit_created_at' => $comment['reddit_created_at']?->format('Y-m-d H:i:s'),
                'raw_data' => $this->encodeJson($comment['raw_data']),
                'fetched_at' => $fetchedAt,
            ];
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 200) as $chunk) {
                RedditComment::query()->upsert(
                    $chunk,
                    ['reddit_comment_id'],
                    [
                        'parent_id',
                        'author',
                        'body',
                        'score',
                        'link_id',
                        'reddit_created_at',
                        'raw_data',
                        'fetched_at',
                    ]
                );
            }
        });

        return count($rows);
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
