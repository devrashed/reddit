<?php

namespace App\Jobs;

use App\Models\Subreddit;
use App\Services\Reddit\InitialCollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RunInitialSubredditCollection implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /**
     * @var list<int>
     */
    public array $backoff;

    public int $timeout;

    public function __construct(public readonly int $subredditId)
    {
        $this->tries = (int) config('reddit.job_tries', 3);
        $this->backoff = array_values((array) config('reddit.job_backoff', [60, 300]));
        $this->timeout = (int) config('reddit.job_timeout', 600);
    }

    public function handle(InitialCollectionService $service): void
    {
        $subreddit = Subreddit::query()->find($this->subredditId);

        if ($subreddit === null) {
            return;
        }

        $lock = Cache::lock(
            'reddit-collection:'.$this->subredditId,
            (int) config('reddit.lock_seconds', 3600)
        );

        if (! $lock->get()) {
            return;
        }

        try {
            $service->run($subreddit);
        } finally {
            $lock->release();
        }
    }
}
