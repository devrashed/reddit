<?php

namespace Tests\Feature;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use App\Jobs\RunInitialSubredditCollection;
use App\Jobs\RunRegularSubredditCollection;
use App\Models\Subreddit;
use App\Services\Reddit\RedditSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedditScheduleCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Cache::flush();
    }

    public function test_command_dispatches_only_eligible_subreddits_once_per_configured_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 08:05:00', 'Asia/Dhaka'));

        $eligible = $this->subreddit('SaaS', SubredditStatus::Active, InitialCollectionStatus::Completed);
        $this->subreddit('Pending', SubredditStatus::Active, InitialCollectionStatus::Pending);
        $this->subreddit('Paused', SubredditStatus::Paused, InitialCollectionStatus::Completed);

        $this->artisan('reddit:dispatch-regular-collection')->assertSuccessful();

        Queue::assertPushedTimes(RunRegularSubredditCollection::class, 1);
        Queue::assertPushed(
            RunRegularSubredditCollection::class,
            fn (RunRegularSubredditCollection $job): bool => $job->subredditId === $eligible->getKey()
        );

        $this->artisan('reddit:dispatch-regular-collection')->assertSuccessful();

        Queue::assertPushedTimes(RunRegularSubredditCollection::class, 1);

        $this->artisan('reddit:dispatch-regular-collection', ['--force' => true])->assertSuccessful();

        Queue::assertPushedTimes(RunRegularSubredditCollection::class, 2);
    }

    public function test_command_does_nothing_outside_configured_collection_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 09:30:00', 'Asia/Dhaka'));
        $this->subreddit('SaaS', SubredditStatus::Active, InitialCollectionStatus::Completed);

        $this->artisan('reddit:dispatch-regular-collection')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_command_respects_customized_collection_times(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 09:04:00', 'Asia/Dhaka'));
        $this->subreddit('SaaS', SubredditStatus::Active, InitialCollectionStatus::Completed);

        app(RedditSettings::class)->save([
            'regular_enabled' => true,
            'regular_frequency' => 1,
            'times' => ['09:00'],
            'http_timeout' => 10,
            'retry_attempts' => 3,
            'historical_months' => 2,
        ]);

        $this->artisan('reddit:dispatch-regular-collection')->assertSuccessful();

        Queue::assertPushedTimes(RunRegularSubredditCollection::class, 1);
    }

    public function test_command_respects_the_disabled_schedule_setting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 08:05:00', 'Asia/Dhaka'));
        $this->subreddit('SaaS', SubredditStatus::Active, InitialCollectionStatus::Completed);

        app(RedditSettings::class)->save([
            'regular_enabled' => false,
            'regular_frequency' => 3,
            'times' => ['08:00', '14:00', '20:00'],
            'http_timeout' => 10,
            'retry_attempts' => 3,
            'historical_months' => 2,
        ]);

        $this->artisan('reddit:dispatch-regular-collection')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_command_can_target_a_single_subreddit_when_forced(): void
    {
        $first = $this->subreddit('SaaS', SubredditStatus::Active, InitialCollectionStatus::Completed);
        $this->subreddit('Startups', SubredditStatus::Active, InitialCollectionStatus::Completed);

        $this->artisan('reddit:dispatch-regular-collection', [
            '--force' => true,
            '--subreddit' => $first->getKey(),
        ])->assertSuccessful();

        Queue::assertPushedTimes(RunRegularSubredditCollection::class, 1);
        Queue::assertPushed(
            RunRegularSubredditCollection::class,
            fn (RunRegularSubredditCollection $job): bool => $job->subredditId === $first->getKey()
        );
    }

    public function test_initial_dispatch_command_queues_pending_and_optionally_failed_collections(): void
    {
        $pending = $this->subreddit('Pending', SubredditStatus::Active, InitialCollectionStatus::Pending);
        $failed = $this->subreddit('Failed', SubredditStatus::Active, InitialCollectionStatus::Failed);
        $this->subreddit('Completed', SubredditStatus::Active, InitialCollectionStatus::Completed);
        $this->subreddit('Paused', SubredditStatus::Paused, InitialCollectionStatus::Pending);

        $this->artisan('reddit:dispatch-initial-collection')->assertSuccessful();

        Queue::assertPushedTimes(RunInitialSubredditCollection::class, 1);
        Queue::assertPushed(
            RunInitialSubredditCollection::class,
            fn (RunInitialSubredditCollection $job): bool => $job->subredditId === $pending->getKey()
        );

        $this->artisan('reddit:dispatch-initial-collection', ['--include-failed' => true])->assertSuccessful();

        Queue::assertPushedTimes(RunInitialSubredditCollection::class, 3);
    }

    private function subreddit(
        string $name,
        SubredditStatus $status,
        InitialCollectionStatus $initialStatus
    ): Subreddit {
        return Subreddit::factory()->create([
            'name' => $name,
            'status' => $status,
            'initial_collection_status' => $initialStatus,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
