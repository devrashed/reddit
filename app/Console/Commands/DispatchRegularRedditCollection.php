<?php

namespace App\Console\Commands;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use App\Jobs\RunRegularSubredditCollection;
use App\Models\Subreddit;
use App\Services\Reddit\RedditSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchRegularRedditCollection extends Command
{
    protected $signature = 'reddit:dispatch-regular-collection
                            {--force : Dispatch regardless of the configured time or enabled setting}
                            {--subreddit= : Dispatch only the given subreddit ID}';

    protected $description = 'Dispatch regular collection jobs for eligible active subreddits';

    public function handle(RedditSettings $settings): int
    {
        $force = (bool) $this->option('force');

        if (! $force) {
            if (! $settings->regularEnabled()) {
                $this->info('Regular Reddit collection scheduling is disabled.');

                return self::SUCCESS;
            }

            $timezone = $settings->timezone();
            $matchedTime = $this->matchingTime($settings->regularTimes(), $timezone);

            if ($matchedTime === null) {
                return self::SUCCESS;
            }

            $graceMinutes = max(1, (int) config('reddit.schedule_grace_minutes', 10));
            $now = now($timezone);
            $slotKey = 'reddit:regular-slot:'.$now->format('Y-m-d').':'.$matchedTime;

            if (! Cache::add($slotKey, true, $now->copy()->addMinutes($graceMinutes + 5))) {
                $this->info("Regular collection slot {$matchedTime} was already dispatched.");

                return self::SUCCESS;
            }
        }

        $subredditId = $this->option('subreddit');

        $query = Subreddit::query()
            ->where('status', SubredditStatus::Active->value)
            ->where('initial_collection_status', InitialCollectionStatus::Completed->value)
            ->when(
                $subredditId !== null && $subredditId !== '',
                fn ($builder) => $builder->whereKey((int) $subredditId)
            )
            ->orderBy('id');

        $dispatched = 0;

        $query->eachById(function (Subreddit $subreddit) use (&$dispatched): void {
            RunRegularSubredditCollection::dispatch($subreddit->getKey());
            $dispatched++;
        });

        $this->info("Dispatched {$dispatched} regular collection job(s).");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $times
     */
    private function matchingTime(array $times, string $timezone): ?string
    {
        $now = now($timezone);
        $graceMinutes = max(1, (int) config('reddit.schedule_grace_minutes', 10));

        foreach ($times as $time) {
            $slot = $now->copy()->startOfDay()->setTimeFromTimeString($time);

            if ($now->greaterThanOrEqualTo($slot) && $now->lessThan($slot->copy()->addMinutes($graceMinutes))) {
                return $time;
            }
        }

        return null;
    }
}
