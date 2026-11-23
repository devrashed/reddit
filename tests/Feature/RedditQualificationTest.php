<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\InitialCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Jobs\CollectPostCommentsJob;
use App\Models\CollectionLog;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Services\Reddit\InitialCollectionService;
use App\Services\Reddit\PostQualificationService;
use App\Services\Reddit\RegularCollectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesRedditCollection;
use Tests\TestCase;

class RedditQualificationTest extends TestCase
{
    use FakesRedditCollection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRedditCollectionFakes();
        Queue::fake();
    }

    public function test_both_engagement_thresholds_are_required_for_qualification(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        $qualifying = $this->createPost($subreddit, 'q1', 5, 5);
        $lowComments = $this->createPost($subreddit, 'q2', 5, 4);
        $lowScore = $this->createPost($subreddit, 'q3', 4, 5);
        $lowBoth = $this->createPost($subreddit, 'q4', 4, 4);

        $newlyQualified = app(PostQualificationService::class)->qualify($subreddit);

        $this->assertSame(1, $newlyQualified);
        $this->assertSame(PostQualificationStatus::Qualified, $qualifying->fresh()->qualification_status);
        $this->assertNotNull($qualifying->fresh()->qualified_at);
        $this->assertSame(PostQualificationStatus::Pending, $lowComments->fresh()->qualification_status);
        $this->assertSame(PostQualificationStatus::Pending, $lowScore->fresh()->qualification_status);
        $this->assertSame(PostQualificationStatus::Pending, $lowBoth->fresh()->qualification_status);
        $this->assertDatabaseCount('ai_analyses', 0);
    }

    public function test_pending_post_is_rechecked_in_batches_and_qualified_when_metrics_improve(): void
    {
        $subreddit = $this->completedSubreddit();
        $post = $this->createPost($subreddit, 'p1', 4, 4, now()->subHours(2));

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response($this->listingResponse([], null), 200),
            'https://www.reddit.com/api/info.json*' => Http::response(
                $this->listingResponse([
                    $this->postData('p1', ['score' => 6, 'num_comments' => 7]),
                ], null),
                200
            ),
        ]);

        app(RegularCollectionService::class)->run($subreddit);

        $post->refresh();

        $this->assertSame(6, $post->score);
        $this->assertSame(7, $post->num_comments);
        $this->assertSame(PostQualificationStatus::Qualified, $post->qualification_status);
        $this->assertNotNull($post->pending_rechecked_at);
        $this->assertSame(CommentCollectionStatus::Pending, $post->comments_collection_status);

        Queue::assertPushedTimes(CollectPostCommentsJob::class, 1);
        Queue::assertPushed(
            CollectPostCommentsJob::class,
            fn (CollectPostCommentsJob $job): bool => $job->postId === $post->getKey()
        );

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->sole();

        $this->assertSame(1, $log->pending_posts_checked);
        $this->assertSame(1, $log->newly_qualified_posts);
        $this->assertSame(1, $log->posts_fetched);
        $this->assertSame(1, $log->posts_updated);
        $this->assertSame(0, $log->posts_created);
        $this->assertSame(0, $log->comments_fetched);
        $this->assertSame(0, $log->failed_operations);
        $this->assertNotNull($log->started_at);
        $this->assertNotNull($log->finished_at);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('ai_analyses', 0);
    }

    public function test_pending_post_stays_pending_when_it_still_fails_a_threshold(): void
    {
        $subreddit = $this->completedSubreddit();
        $post = $this->createPost($subreddit, 'p1', 4, 4, now()->subHours(2));

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response($this->listingResponse([], null), 200),
            'https://www.reddit.com/api/info.json*' => Http::response(
                $this->listingResponse([
                    $this->postData('p1', ['score' => 50, 'num_comments' => 4]),
                ], null),
                200
            ),
        ]);

        app(RegularCollectionService::class)->run($subreddit);

        $post->refresh();

        $this->assertSame(50, $post->score);
        $this->assertSame(4, $post->num_comments);
        $this->assertSame(PostQualificationStatus::Pending, $post->qualification_status);
        $this->assertNotNull($post->pending_rechecked_at);

        Queue::assertNothingPushed();

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->sole();
        $this->assertSame(1, $log->pending_posts_checked);
        $this->assertSame(0, $log->newly_qualified_posts);
    }

    public function test_initial_collection_qualifies_posts_and_queues_comment_collection(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $listing = $this->listingResponse([
            $this->postData('p1', ['score' => 5, 'num_comments' => 5]),
            $this->postData('p2', ['score' => 5, 'num_comments' => 4]),
        ], null);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response($listing, 200),
        ]);

        app(InitialCollectionService::class)->run($subreddit);

        $qualified = RedditPost::query()->where('reddit_id', 'p1')->sole();
        $pending = RedditPost::query()->where('reddit_id', 'p2')->sole();

        $this->assertSame(InitialCollectionStatus::Completed, $subreddit->fresh()->initial_collection_status);
        $this->assertSame(PostQualificationStatus::Qualified, $qualified->qualification_status);
        $this->assertSame(PostQualificationStatus::Pending, $pending->qualification_status);
        $this->assertSame(CommentCollectionStatus::Pending, $qualified->comments_collection_status);

        Queue::assertPushedTimes(CollectPostCommentsJob::class, 1);
        Queue::assertPushed(
            CollectPostCommentsJob::class,
            fn (CollectPostCommentsJob $job): bool => $job->postId === $qualified->getKey()
        );

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_INITIAL)->sole();
        $this->assertSame(1, $log->newly_qualified_posts);
        $this->assertSame(1, $log->qualified_posts);
        $this->assertSame(0, $log->comments_fetched);
        $this->assertStringContainsString('Queued 1 comment collection job', (string) $log->notes);
    }

    public function test_completed_comment_collection_is_not_queued_again(): void
    {
        $subreddit = $this->completedSubreddit();

        RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => 'done1',
            'title' => 'Already processed',
            'score' => 10,
            'num_comments' => 10,
            'permalink' => '/r/SaaS/comments/done1/post/',
            'reddit_created_at' => now()->subDay(),
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Completed,
        ]);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response($this->listingResponse([], null), 200),
        ]);

        app(RegularCollectionService::class)->run($subreddit);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('reddit_comments', 0);
    }

    private function createPost(
        Subreddit $subreddit,
        string $redditId,
        int $score,
        int $numComments,
        $lastFetchedAt = null
    ): RedditPost {
        return RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => $redditId,
            'title' => 'Post '.$redditId,
            'selftext' => 'Body '.$redditId,
            'score' => $score,
            'num_comments' => $numComments,
            'permalink' => "/r/SaaS/comments/{$redditId}/post/",
            'post_url' => "https://www.reddit.com/r/SaaS/comments/{$redditId}/post/",
            'reddit_created_at' => now()->subDay(),
            'first_fetched_at' => now()->subHours(3),
            'last_fetched_at' => $lastFetchedAt ?? now()->subHours(3),
            'qualification_status' => PostQualificationStatus::Pending,
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
