<?php

namespace Tests\Feature;

use App\Models\CollectionLog;
use App\Models\Subreddit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class CollectionLogPageTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    public function test_administrators_can_view_collection_logs(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        CollectionLog::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'collection_type' => CollectionLog::TYPE_INITIAL,
            'status' => CollectionLog::STATUS_COMPLETED,
            'posts_fetched' => 20,
            'posts_created' => 18,
            'posts_updated' => 2,
            'qualified_posts' => 4,
            'pending_posts_checked' => 12,
            'newly_qualified_posts' => 4,
            'comments_fetched' => 30,
            'failed_operations' => 1,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
            'notes' => 'Pagination stopped at the configured 2-month cutoff.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.collection-logs.index'))
            ->assertOk()
            ->assertSee('r/SaaS')
            ->assertSee('Initial')
            ->assertSee('Completed')
            ->assertSee('Pending checked: 12')
            ->assertSee('End:')
            ->assertSee('Duration:')
            ->assertSee('Failed ops: 1')
            ->assertSee('30');
    }

    public function test_collection_logs_can_be_filtered_by_type(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        CollectionLog::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'collection_type' => CollectionLog::TYPE_REGULAR,
            'status' => CollectionLog::STATUS_FAILED,
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => 'HTTP 500',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.collection-logs.index', ['type' => 'initial']))
            ->assertOk()
            ->assertSee('No collection runs match the current filters.');
    }
}
