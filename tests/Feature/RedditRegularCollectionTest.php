<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\InitialCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Exceptions\RedditCollectionException;
use App\Jobs\RunRegularSubredditCollection;
use App\Models\CollectionLog;
use App\Models\RedditComment;
use App\Models\RedditPost;
use App\Models\Subreddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
    }

    public function test_regular_collection_updates_metrics_qualifies_posts_and_stores_nested_comments(): void
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
            $this->postData('b1', ['score' => 8, 'num_comments' => 4]),
            $this->postData('c1', ['score' => 0, 'num_comments' => 0]),
        ], null);

        $nested = $this->commentData('c2', ['parent_id' => 't1_c1', 'body' => 'Nested reply']);
        $topLevel = $this->commentData('c1', ['parent_id' => 't3_b1', 'body' => 'Top comment'], [$nested]);
        $more = $this->moreData(['c3'], 1);

        $comments = $this->commentsResponse([$topLevel, $more]);
        $moreResponse = [
            'json' => [
                'errors' => [],
                'data' => [
                    'things' => [
                        $this->commentData('c3', ['parent_id' => 't3_b1', 'body' => 'Expanded comment']),
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response($listing, 200),
            'https://www.reddit.com/r/SaaS/comments/b1/post_b1.json*' => Http::response($comments, 200),
            'https://www.reddit.com/api/morechildren.json*' => Http::response($moreResponse, 200),
        ]);

        RunRegularSubredditCollection::dispatchSync($subreddit->getKey());

        Http::assertSentCount(3);
        $this->assertDatabaseCount('reddit_posts', 3);
        $this->assertDatabaseCount('reddit_comments', 3);

        $existing->refresh();
        $this->assertSame('Updated title', $existing->title);
        $this->assertSame(25, $existing->score);
        $this->assertSame(6, $existing->num_comments);
        $this->assertSame(PostQualificationStatus::Qualified, $existing->qualification_status);
        $this->assertSame(CommentCollectionStatus::Completed, $existing->comments_collection_status);

        $qualified = RedditPost::query()->where('reddit_id', 'b1')->sole();
        $rejected = RedditPost::query()->where('reddit_id', 'c1')->sole();

        $this->assertSame(PostQualificationStatus::Qualified, $qualified->qualification_status);
        $this->assertSame(CommentCollectionStatus::Completed, $qualified->comments_collection_status);
        $this->assertSame(1, $qualified->comment_collection_attempts);
        $this->assertNotNull($qualified->comments_collected_at);
        $this->assertSame(PostQualificationStatus::Rejected, $rejected->qualification_status);
        $this->assertSame(CommentCollectionStatus::Pending, $rejected->comments_collection_status);

        $nestedComment = RedditComment::query()->where('reddit_comment_id', 'c2')->sole();
        $this->assertSame('t1_c1', $nestedComment->parent_id);
        $this->assertSame('Nested reply', $nestedComment->body);
        $this->assertSame($qualified->getKey(), $nestedComment->post_id);
        $this->assertDatabaseHas('reddit_comments', ['reddit_comment_id' => 'c3', 'body' => 'Expanded comment']);

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->sole();

        $this->assertSame(CollectionLog::STATUS_COMPLETED, $log->status);
        $this->assertSame(3, $log->posts_fetched);
        $this->assertSame(2, $log->posts_created);
        $this->assertSame(1, $log->posts_updated);
        $this->assertSame(1, $log->qualified_posts);
        $this->assertSame(3, $log->comments_fetched);
        $this->assertStringContainsString('more-comment', (string) $log->notes);
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

        RunRegularSubredditCollection::dispatchSync($subreddit->getKey());
        RunRegularSubredditCollection::dispatchSync($subreddit->getKey());

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

        RunRegularSubredditCollection::dispatchSync($subreddit->getKey());

        Http::assertNothingSent();
        $this->assertDatabaseCount('collection_logs', 0);
        $this->assertSame(InitialCollectionStatus::Running, $subreddit->fresh()->initial_collection_status);
    }

    public function test_failed_comment_collection_is_logged_and_retried_on_a_later_run(): void
    {
        $subreddit = $this->completedSubreddit();

        $post = RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => 'b1',
            'title' => 'Needs comments',
            'score' => 4,
            'num_comments' => 3,
            'permalink' => '/r/SaaS/comments/b1/post_b1/',
            'reddit_created_at' => now()->subDay(),
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Pending,
        ]);

        $successfulComments = $this->commentsResponse([
            $this->commentData('r1', ['parent_id' => 't3_b1', 'body' => 'Recovered comment']),
        ]);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response($this->listingResponse([], null), 200),
            'https://www.reddit.com/r/SaaS/comments/b1/post_b1.json*' => Http::sequence()
                ->push(['error' => 'forbidden'], 403)
                ->push($successfulComments, 200),
        ]);

        RunRegularSubredditCollection::dispatchSync($subreddit->getKey());

        $post->refresh();

        $this->assertSame(CommentCollectionStatus::Failed, $post->comments_collection_status);
        $this->assertSame(1, $post->comment_collection_attempts);
        $this->assertDatabaseCount('reddit_comments', 0);

        $failedLog = CollectionLog::query()->latest('id')->first();
        $this->assertSame(CollectionLog::STATUS_COMPLETED, $failedLog->status);
        $this->assertStringContainsString('failed', (string) $failedLog->error_message);

        RunRegularSubredditCollection::dispatchSync($subreddit->getKey());

        $post->refresh();

        $this->assertSame(CommentCollectionStatus::Completed, $post->comments_collection_status);
        $this->assertSame(2, $post->comment_collection_attempts);
        $this->assertDatabaseHas('reddit_comments', ['reddit_comment_id' => 'r1']);
        $this->assertSame(2, CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->count());
    }

    public function test_regular_collection_marks_the_log_failed_when_listing_requests_fail(): void
    {
        $subreddit = $this->completedSubreddit();

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response(['error' => 'server'], 500),
        ]);

        try {
            RunRegularSubredditCollection::dispatchSync($subreddit->getKey());
            $this->fail('Expected regular collection to fail.');
        } catch (RedditCollectionException) {
            // Expected.
        }

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_REGULAR)->sole();

        $this->assertSame(CollectionLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('HTTP 500', (string) $log->error_message);
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
