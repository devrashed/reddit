<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\InitialCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Exceptions\RedditCollectionException;
use App\Jobs\CollectPostCommentsJob;
use App\Models\CollectionLog;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Services\Reddit\RegularCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesRedditCollection;
use Tests\TestCase;

class RedditRegularCollectionTest extends TestCase
{
    use FakesRedditCollection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRedditCollectionFakes();
        Queue::fake();
    }

    public function test_regular_collection_updates_metrics_qualifies_posts_and_queues_comment_collection(): void
    {
        $subreddit = $this->completedSubreddit();

        $existing = RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => 'a1',
            'title' => 'Original title',
            'selftext' => 'Body a1',
            'author' => 'author_a1',
            'score' => 4,
            'num_comments' => 1,
            'permalink' => '/r/SaaS/comments/a1/post_a1/',
            'reddit_created_at' => now()->subDays(2),
            'last_fetched_at' => now()->subDay(),
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Completed,
        ]);

        $listing = $this->listingResponse([
            $this->postData('a1', ['title' => 'Updated title', 'score' => 25, 'num_comments' => 6]),
            $this->postData('b1', ['score' => 8, 'num_comments' => 6]),
            $this->postData('c1', ['score' => 4, 'num_comments' => 4]),
        ], null);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response($listing, 200),
        ]);

        app(RegularCollectionService::class)->run($subreddit);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('reddit_posts', 3);
        $this->assertDatabaseCount('reddit_comments', 0);

        $existing->refresh();
        $this->assertSame('Updated title', $existing->title);
        $this->assertSame(25, $existing->score);
        $this->assertSame(6, $existing->num_comments);
        $this->assertSame(PostQualificationStatus::Qualified, $existing->qualification_status);
        $this->assertSame(CommentCollectionStatus::Completed, $existing->comments_collection_status);

        $qualified = RedditPost::query()->where('reddit_id', 'b1')->sole();
        $pending = RedditPost::query()->where('reddit_id', 'c1')->sole();

        $this->assertSame(PostQualificationStatus::Qualified, $qualified->qualification_status);
        $this->assertSame(CommentCollectionStatus::Pending, $qualified->comments_collection_status);
        $this->assertSame(0, $qualified->comment_collection_attempts);
        $this->assertSame(PostQualificationStatus::Pending, $pending->qualification_status);
        $this->assertSame(CommentCollectionStatus::Pending, $pending->comments_collection_status);

        Queue::assertPushedTimes(CollectPostCommentsJob::class, 1);
        Queue::assertPushed(
            CollectPostCommentsJob::class,
            fn (CollectPostCommentsJob $job): bool => $job->postId === $qualified->getKey()
        );

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->sole();

        $this->assertSame(CollectionLog::STATUS_COMPLETED, $log->status);
        $this->assertSame(3, $log->posts_fetched);
        $this->assertSame(2, $log->posts_created);
        $this->assertSame(1, $log->posts_updated);
        $this->assertSame(1, $log->qualified_posts);
        $this->assertSame(1, $log->newly_qualified_posts);
        $this->assertSame(0, $log->pending_posts_checked);
        $this->assertSame(0, $log->comments_fetched);
        $this->assertSame(0, $log->failed_operations);
        $this->assertStringContainsString('Queued 1 comment collection job', (string) $log->notes);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->finished_at);
        $this->assertNotNull($subreddit->fresh()->last_collected_at);
    }

    public function test_regular_collection_does_not_duplicate_posts_and_updates_metrics_across_runs(): void
    {
        $subreddit = $this->completedSubreddit();

        RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => 'a1',
            'title' => 'Existing post',
            'score' => 1,
            'num_comments' => 1,
            'permalink' => '/r/SaaS/comments/a1/post_a1/',
            'reddit_created_at' => now()->subDay(),
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Completed,
        ]);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::sequence()
                ->push($this->listingResponse([$this->postData('a1', ['score' => 10])], null), 200)
                ->push($this->listingResponse([$this->postData('a1', ['score' => 22])], null), 200),
        ]);

        app(RegularCollectionService::class)->run($subreddit);
        app(RegularCollectionService::class)->run($subreddit);

        $this->assertDatabaseCount('reddit_posts', 1);
        $this->assertSame(22, RedditPost::query()->where('reddit_id', 'a1')->sole()->score);
        $this->assertSame(2, CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->count());

        $latestLog = CollectionLog::query()->latest('id')->first();
        $this->assertSame(0, $latestLog->posts_created);
        $this->assertSame(1, $latestLog->posts_updated);
        Http::assertSentCount(2);
    }

    public function test_regular_collection_does_not_run_until_initial_collection_is_completed(): void
    {
        $subreddit = Subreddit::factory()->create([
            'name' => 'SaaS',
            'initial_collection_status' => InitialCollectionStatus::Running,
        ]);

        Http::fake();

        app(RegularCollectionService::class)->run($subreddit);

        Http::assertNothingSent();
        $this->assertDatabaseCount('collection_logs', 0);
        $this->assertSame(InitialCollectionStatus::Running, $subreddit->fresh()->initial_collection_status);
    }

    public function test_regular_collection_marks_the_log_failed_when_listing_requests_fail(): void
    {
        $subreddit = $this->completedSubreddit();

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response(['error' => 'server'], 500),
        ]);

        try {
            app(RegularCollectionService::class)->run($subreddit);
            $this->fail('Expected regular collection to fail.');
        } catch (RedditCollectionException) {
            // Expected.
        }

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->sole();

        $this->assertSame(CollectionLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('HTTP 500', (string) $log->error_message);
        $this->assertSame(1, $log->failed_operations);
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
