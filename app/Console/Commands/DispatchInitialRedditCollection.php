<?php

namespace App\Console\Commands;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use App\Jobs\RunInitialSubredditCollection;
use App\Models\Subreddit;
use Illuminate\Console\Command;

class DispatchInitialRedditCollection extends Command
{
    protected $signature = 'reddit:dispatch-initial-collection
                            {--subreddit= : Dispatch only the given subreddit ID}
                            {--include-failed : Also retry failed initial collections}';

    protected $description = 'Dispatch initial historical collection jobs for pending subreddits';

    public function handle(): int
    {
        $statuses = [InitialCollectionStatus::Pending->value];

        if ((bool) $this->option('include-failed')) {
            $statuses[] = InitialCollectionStatus::Failed->value;
        }

        $subredditId = $this->option('subreddit');

        $query = Subreddit::query()
            ->where('status', SubredditStatus::Active->value)
            ->whereIn('initial_collection_status', $statuses)
            ->when(
                $subredditId !== null && $subredditId !== '',
                fn ($builder) => $builder->whereKey((int) $subredditId)
            )
            ->orderBy('id');

        $dispatched = 0;

        $query->eachById(function (Subreddit $subreddit) use (&$dispatched): void {
            RunInitialSubredditCollection::dispatch($subreddit->getKey());
            $dispatched++;
        });

        $this->info("Dispatched {$dispatched} initial collection job(s).");

        return self::SUCCESS;
    }
}
