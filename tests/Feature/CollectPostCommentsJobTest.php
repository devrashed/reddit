<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\InitialCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Exceptions\RedditCollectionException;
use App\Jobs\CollectPostCommentsJob;
use App\Models\CollectionLog;
use App\Models\RedditComment;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Services\Reddit\CollectionLogService;
use App\Services\Reddit\CommentCollectionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRedditCollection;
use Tests\TestCase;

class CollectPostCommentsJobTest extends TestCase
{
    use FakesRedditCollection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRedditCollectionFakes();
    }

    public function test_comment_job_collects_nested_and_more_comments_and_is_idempotent(): void
    {
        $subreddit = $this->completedSubreddit();
        $post = $this->qualifiedPost($subreddit, 'p1');

        $nested = $this->commentData('c2', ['parent_id' => 't1_c1', 'body' => 'Nested reply']);
        $topLevel = $this->commentData('c1', ['parent_id' => 't3_p1', 'body' => 'Top comment'], [$nested]);
        $more = $this->moreData(['c3'], 1);

        Http::fake([
            'https://www.reddit.com/r/SaaS/comments/p1/post.json*' => Http::response(
                $this->commentsResponse([$topLevel, $more]),
                200
            ),
            'https://www.reddit.com/api/morechildren.json*' => Http::response([
                'json' => [
                    'errors' => [],
                    'data' => [
                        'things' => [
                            $this->commentData('c3', ['parent_id' => 't3_p1', 'body' => 'Expanded comment']),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $job = new CollectPostCommentsJob($post->getKey());

        $this->assertInstanceOf(ShouldBeUnique::class, $job);

        $job->handle(
            app(CommentCollectionService::class),
            app(CollectionLogService::class)
        );

        $post->refresh();

        $this->assertSame(CommentCollectionStatus::Completed, $post->comments_collection_status);
        $this->assertSame(1, $post->comment_collection_attempts);
        $this->assertNotNull($post->comments_collected_at);
        $this->assertDatabaseCount('reddit_comments', 3);

        $nestedComment = RedditComment::query()->where('reddit_comment_id', 'c2')->sole();
        $this->assertSame('t1_c1', $nestedComment->parent_id);
        $this->assertSame($post->getKey(), $nestedComment->post_id);
        $this->assertDatabaseHas('reddit_comments', ['reddit_comment_id' => 'c3', 'body' => 'Expanded comment']);

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_COMMENTS)->sole();

        $this->assertSame(CollectionLog::STATUS_COMPLETED, $log->status);
        $this->assertSame(3, $log->comments_fetched);
        $this->assertSame(0, $log->failed_operations);
        $this->assertStringContainsString('t3_p1', (string) $log->notes);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->finished_at);
        $this->assertDatabaseCount('ai_analyses', 0);

        Http::assertSentCount(2);

        (new CollectPostCommentsJob($post->getKey()))->handle(
            app(CommentCollectionService::class),
            app(CollectionLogService::class)
        );

        Http::assertSentCount(2);
        $this->assertDatabaseCount('reddit_comments', 3);
        $this->assertSame(1, CollectionLog::query()->where('collection_type', CollectionLog::TYPE_COMMENTS)->count());
    }

    public function test_failed_comment_job_records_error_and_can_retry_without_duplicates(): void
    {
        $subreddit = $this->completedSubreddit();
        $post = $this->qualifiedPost($subreddit, 'p1');

        $success = $this->commentsResponse([
            $this->commentData('r1', ['parent_id' => 't3_p1', 'body' => 'Recovered comment']),
        ]);

        Http::fake([
            'https://www.reddit.com/r/SaaS/comments/p1/post.json*' => Http::sequence()
                ->push(['error' => 'forbidden'], 403)
                ->push($success, 200),
        ]);

        try {
            (new CollectPostCommentsJob($post->getKey()))->handle(
                app(CommentCollectionService::class),
                app(CollectionLogService::class)
            );
            $this->fail('Expected the comments job to fail.');
        } catch (RedditCollectionException) {
            // Expected.
        }

        $post->refresh();

        $this->assertSame(CommentCollectionStatus::Failed, $post->comments_collection_status);
        $this->assertSame(1, $post->comment_collection_attempts);
        $this->assertDatabaseCount('reddit_comments', 0);

        $failedLog = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_COMMENTS)->sole();
        $this->assertSame(CollectionLog::STATUS_FAILED, $failedLog->status);
        $this->assertSame(1, $failedLog->failed_operations);
        $this->assertStringContainsString('denied access', (string) $failedLog->error_message);

        (new CollectPostCommentsJob($post->getKey()))->handle(
            app(CommentCollectionService::class),
            app(CollectionLogService::class)
        );

        $post->refresh();

        $this->assertSame(CommentCollectionStatus::Completed, $post->comments_collection_status);
        $this->assertSame(2, $post->comment_collection_attempts);
        $this->assertDatabaseHas('reddit_comments', ['reddit_comment_id' => 'r1']);
        $this->assertSame(2, CollectionLog::query()->where('collection_type', CollectionLog::TYPE_COMMENTS)->count());
        Http::assertSentCount(2);
    }

    public function test_comment_job_skips_posts_that_are_not_qualified(): void
    {
        $subreddit = $this->completedSubreddit();

        $post = RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => 'pending1',
            'title' => 'Pending post',
            'score' => 4,
            'num_comments' => 4,
            'permalink' => '/r/SaaS/comments/pending1/post/',
            'reddit_created_at' => now()->subDay(),
            'qualification_status' => PostQualificationStatus::Pending,
            'comments_collection_status' => CommentCollectionStatus::Pending,
        ]);

        Http::fake();

        (new CollectPostCommentsJob($post->getKey()))->handle(
            app(CommentCollectionService::class),
            app(CollectionLogService::class)
        );

        Http::assertNothingSent();
        $this->assertSame(CommentCollectionStatus::Pending, $post->fresh()->comments_collection_status);
        $this->assertDatabaseCount('collection_logs', 0);
    }

    public function test_comment_job_has_bounded_retries_and_backoff(): void
    {
        $job = new CollectPostCommentsJob(123);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff);
        $this->assertSame(600, $job->timeout);
        $this->assertSame('reddit-post-comments:123', $job->uniqueId());
    }

    private function qualifiedPost(Subreddit $subreddit, string $redditId): RedditPost
    {
        return RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => $redditId,
            'title' => 'Qualified post',
            'selftext' => 'Body',
            'score' => 10,
            'num_comments' => 6,
            'permalink' => "/r/SaaS/comments/{$redditId}/post/",
            'post_url' => "https://www.reddit.com/r/SaaS/comments/{$redditId}/post/",
            'reddit_created_at' => now()->subDay(),
            'first_fetched_at' => now()->subDay(),
            'last_fetched_at' => now()->subHour(),
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Pending,
        ]);
    }

    private function completedSubreddit(): Subreddit
    {
        return Subreddit::factory()->create([
            'name' => 'SaaS',
            'status' => SubredditStatus::Active,
            'initial_collection_status' => InitialCollectionStatus::Completed,
            'initial_collection_started_at' => now()->subDay(),
            'initial_collection_completed_at' => now()->subHours(12),
        ]);
    }
}
