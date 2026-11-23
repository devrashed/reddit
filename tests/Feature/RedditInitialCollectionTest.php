<?php

namespace Tests\Feature;

use App\Enums\InitialCollectionStatus;
use App\Exceptions\RedditCollectionException;
use App\Jobs\RunInitialSubredditCollection;
use App\Models\CollectionLog;
use App\Models\RedditPost;
use App\Models\Subreddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesRedditCollection;
use Tests\TestCase;

class RedditInitialCollectionTest extends TestCase
{
    use FakesRedditCollection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareRedditCollectionFakes();
    }

    public function test_initial_collection_paginates_filters_the_cutoff_and_stores_posts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 12:00:00', 'Asia/Dhaka'));

        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $pageOne = $this->listingResponse([
            $this->postData('a1', ['created_utc' => now()->subDays(5)->timestamp]),
            $this->postData('a2', ['created_utc' => now()->subDays(30)->timestamp]),
        ], 't3_cursor1');
        $pageTwo = $this->listingResponse([
            $this->postData('a3', ['created_utc' => now()->subDays(50)->timestamp]),
            $this->postData('old1', ['created_utc' => now()->subDays(70)->timestamp]),
        ], 't3_cursor2');

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::sequence()
                ->push($pageOne, 200)
                ->push($pageTwo, 200),
        ]);

        RunInitialSubredditCollection::dispatchSync($subreddit->getKey());

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'after=t3_cursor1'));
        Http::assertSent(fn (Request $request): bool => in_array(
            config('reddit.user_agent'),
            $request->header('User-Agent'),
            true
        ));

        $subreddit->refresh();

        $this->assertSame(InitialCollectionStatus::Completed, $subreddit->initial_collection_status);
        $this->assertSame(2, $subreddit->initial_collection_pages);
        $this->assertSame(4, $subreddit->initial_collection_posts_fetched);
        $this->assertSame('t3_cursor2', $subreddit->initial_collection_cursor);
        $this->assertNotNull($subreddit->initial_collection_started_at);
        $this->assertNotNull($subreddit->initial_collection_completed_at);
        $this->assertNotNull($subreddit->last_collected_at);

        $this->assertDatabaseCount('reddit_posts', 3);
        $this->assertDatabaseMissing('reddit_posts', ['reddit_id' => 'old1']);

        $post = RedditPost::query()->where('reddit_id', 'a1')->sole();

        $this->assertSame('Post a1', $post->title);
        $this->assertSame('Body a1', $post->selftext);
        $this->assertSame('author_a1', $post->author);
        $this->assertSame(5, $post->score);
        $this->assertSame(3, $post->num_comments);
        $this->assertSame('/r/SaaS/comments/a1/post_a1/', $post->permalink);
        $this->assertTrue($post->is_self);
        $this->assertEqualsWithDelta(0.92, (float) $post->upvote_ratio, 0.0001);
        $this->assertSame('https://www.reddit.com/r/SaaS/comments/a1/post_a1/', $post->post_url);
        $this->assertNotNull($post->reddit_created_at);
        $this->assertNotNull($post->first_fetched_at);
        $this->assertNotNull($post->last_fetched_at);
        $this->assertSame('a1', $post->raw_data['id']);

        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_INITIAL)->sole();

        $this->assertSame(CollectionLog::STATUS_COMPLETED, $log->status);
        $this->assertSame(4, $log->posts_fetched);
        $this->assertSame(3, $log->posts_created);
        $this->assertSame(0, $log->posts_updated);
        $this->assertStringContainsString('cutoff', (string) $log->notes);
    }

    public function test_initial_collection_logs_a_limitation_when_the_listing_ends_early(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response(
                $this->listingResponse([$this->postData('b1')], null),
                200
            ),
        ]);

        RunInitialSubredditCollection::dispatchSync($subreddit->getKey());

        $subreddit->refresh();
        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_INITIAL)->sole();

        $this->assertSame(InitialCollectionStatus::Completed, $subreddit->initial_collection_status);
        $this->assertStringContainsString('listing ended', (string) $log->notes);
        $this->assertStringContainsString('incomplete', (string) $log->notes);
    }

    public function test_completed_initial_collection_is_never_run_again(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response(
                $this->listingResponse([$this->postData('c1')], null),
                200
            ),
        ]);

        RunInitialSubredditCollection::dispatchSync($subreddit->getKey());

        Http::fake();

        RunInitialSubredditCollection::dispatchSync($subreddit->getKey());

        Http::assertNothingSent();
        $this->assertDatabaseCount('reddit_posts', 1);
        $this->assertDatabaseCount('collection_logs', 1);
    }

    public function test_failed_initial_collection_can_resume_from_the_saved_cursor(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $pageOne = $this->listingResponse([
            $this->postData('d1'),
            $this->postData('d2'),
        ], 't3_cursor1');
        $pageTwo = $this->listingResponse([$this->postData('d3')], null);
        $calls = 0;

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => function (Request $request) use (&$calls, $pageOne, $pageTwo) {
                $calls++;

                if ($calls === 1) {
                    return Http::response($pageOne, 200);
                }

                if ($calls <= 4) {
                    return Http::response(['error' => 'server'], 500);
                }

                return Http::response($pageTwo, 200);
            },
        ]);

        try {
            RunInitialSubredditCollection::dispatchSync($subreddit->getKey());
            $this->fail('Expected the initial collection to fail.');
        } catch (RedditCollectionException) {
            // Expected.
        }

        $this->assertSame(4, $calls);

        $subreddit->refresh();

        $this->assertSame(InitialCollectionStatus::Failed, $subreddit->initial_collection_status);
        $this->assertSame(1, $subreddit->initial_collection_pages);
        $this->assertSame('t3_cursor1', $subreddit->initial_collection_cursor);
        $this->assertDatabaseCount('reddit_posts', 2);
        $this->assertDatabaseHas('collection_logs', [
            'collection_type' => CollectionLog::TYPE_INITIAL,
            'status' => CollectionLog::STATUS_FAILED,
        ]);

        RunInitialSubredditCollection::dispatchSync($subreddit->getKey());

        $subreddit->refresh();

        $this->assertSame(5, $calls);
        $this->assertSame(InitialCollectionStatus::Completed, $subreddit->initial_collection_status);
        $this->assertSame(2, $subreddit->initial_collection_pages);
        $this->assertDatabaseCount('reddit_posts', 3);
        $this->assertSame(2, CollectionLog::query()->where('collection_type', CollectionLog::TYPE_INITIAL)->count());
        Http::assertSentCount(5);
    }

    public function test_http_403_marks_the_initial_collection_failed_without_retrying(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        try {
            RunInitialSubredditCollection::dispatchSync($subreddit->getKey());
            $this->fail('Expected an access-denied failure.');
        } catch (RedditCollectionException $exception) {
            $this->assertStringContainsString('denied access', $exception->getMessage());
        }

        $subreddit->refresh();
        $log = CollectionLog::query()->where('collection_type', CollectionLog::TYPE_INITIAL)->sole();

        $this->assertSame(InitialCollectionStatus::Failed, $subreddit->initial_collection_status);
        $this->assertSame(CollectionLog::STATUS_FAILED, $log->status);
        $this->assertStringContainsString('denied access', (string) $log->error_message);
        Http::assertSentCount(1);
    }

    public function test_invalid_json_is_retried_and_then_fails_safely(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::sequence()
                ->push('<html>blocked</html>', 200, ['Content-Type' => 'text/html'])
                ->push('<html>blocked</html>', 200, ['Content-Type' => 'text/html'])
                ->push('<html>blocked</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        try {
            RunInitialSubredditCollection::dispatchSync($subreddit->getKey());
            $this->fail('Expected invalid JSON to fail.');
        } catch (RedditCollectionException $exception) {
            $this->assertStringContainsString('invalid JSON', $exception->getMessage());
        }

        $this->assertSame(InitialCollectionStatus::Failed, $subreddit->fresh()->initial_collection_status);
        $this->assertDatabaseHas('collection_logs', [
            'collection_type' => CollectionLog::TYPE_INITIAL,
            'status' => CollectionLog::STATUS_FAILED,
        ]);
        Http::assertSentCount(3);
    }

    public function test_rate_limited_responses_are_retried_and_logged_as_completed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 12:00:00', 'Asia/Dhaka'));

        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::sequence()
                ->push(['error' => 'rate limited'], 429, ['Retry-After' => '2'])
                ->push($this->listingResponse([$this->postData('e1')], null), 200),
        ]);

        RunInitialSubredditCollection::dispatchSync($subreddit->getKey());

        $this->assertSame(InitialCollectionStatus::Completed, $subreddit->fresh()->initial_collection_status);
        $this->assertDatabaseHas('reddit_posts', ['reddit_id' => 'e1']);
        $this->assertDatabaseHas('collection_logs', [
            'collection_type' => CollectionLog::TYPE_INITIAL,
            'status' => CollectionLog::STATUS_COMPLETED,
        ]);
        Http::assertSentCount(2);
    }

    public function test_concurrent_collection_for_the_same_subreddit_is_prevented_by_a_lock(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $lock = Cache::lock('reddit-collection:'.$subreddit->getKey(), 60);

        $this->assertTrue($lock->get());

        Http::fake();

        RunInitialSubredditCollection::dispatchSync($subreddit->getKey());

        Http::assertNothingSent();
        $this->assertSame(InitialCollectionStatus::Pending, $subreddit->fresh()->initial_collection_status);
        $this->assertDatabaseCount('collection_logs', 0);

        $lock->release();
    }

    public function test_network_failures_are_retried_then_fail_safely(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        Http::fake([
            'https://www.reddit.com/r/SaaS.json*' => Http::failedConnection('Connection timed out.'),
        ]);

        try {
            RunInitialSubredditCollection::dispatchSync($subreddit->getKey());
            $this->fail('Expected a network failure.');
        } catch (RedditCollectionException $exception) {
            $this->assertStringContainsString('Network failure', $exception->getMessage());
        }

        $this->assertSame(InitialCollectionStatus::Failed, $subreddit->fresh()->initial_collection_status);
        $this->assertDatabaseHas('collection_logs', [
            'collection_type' => CollectionLog::TYPE_INITIAL,
            'status' => CollectionLog::STATUS_FAILED,
        ]);
        Http::assertSentCount(3);
    }

    public function test_queued_collection_job_has_bounded_retries_and_a_timeout(): void
    {
        $job = new RunInitialSubredditCollection(123);

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff);
        $this->assertSame(600, $job->timeout);
    }
}
