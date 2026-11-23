<?php

namespace Tests\Feature;

use App\Enums\InitialCollectionStatus;
use App\Enums\SubredditStatus;
use App\Jobs\RunInitialSubredditCollection;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class SubredditManagementTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_guests_are_redirected_from_subreddit_management(): void
    {
        $this->get('/admin/subreddits')->assertRedirect('/login');
    }

    public function test_non_admin_users_cannot_manage_subreddits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/subreddits')
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('admin.subreddits.store'), ['name' => 'SaaS'])
            ->assertForbidden();

        $this->assertDatabaseCount('subreddits', 0);
    }

    public function test_administrators_can_view_the_subreddit_index(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create([
            'name' => 'SaaS',
            'display_name' => 'SaaS Founders',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subreddits.index'))
            ->assertOk()
            ->assertSee('r/SaaS')
            ->assertSee('SaaS Founders')
            ->assertSee($subreddit->url)
            ->assertSee('Active')
            ->assertSee('Pending');
    }

    public function test_administrators_can_add_a_subreddit(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->post(route('admin.subreddits.store'), [
            'name' => 'r/SaaS',
            'display_name' => 'SaaS Founders',
            'description' => 'Recurring software pain points.',
            'url' => 'https://evil.example/r/SaaS.json',
        ]);

        $subreddit = Subreddit::query()->sole();

        $response
            ->assertRedirect(route('admin.subreddits.show', $subreddit))
            ->assertSessionHas('success');

        $this->assertSame('SaaS', $subreddit->name);
        $this->assertSame('SaaS Founders', $subreddit->display_name);
        $this->assertSame('Recurring software pain points.', $subreddit->description);
        $this->assertSame('https://www.reddit.com/r/SaaS.json', $subreddit->url);
        $this->assertSame(SubredditStatus::Active, $subreddit->status);
        $this->assertSame(InitialCollectionStatus::Pending, $subreddit->initial_collection_status);
        $this->assertNull($subreddit->initial_collection_started_at);
        $this->assertNull($subreddit->initial_collection_completed_at);
        $this->assertNull($subreddit->last_collected_at);

        Queue::assertPushed(
            RunInitialSubredditCollection::class,
            fn (RunInitialSubredditCollection $job): bool => $job->subredditId === $subreddit->getKey()
        );
    }

    public function test_subreddit_names_are_normalized_from_pasted_reddit_urls(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->post(route('admin.subreddits.store'), [
            'name' => 'https://www.reddit.com/r/SideProject.json',
        ])->assertRedirect();

        $subreddit = Subreddit::query()->sole();

        $this->assertSame('SideProject', $subreddit->name);
        $this->assertSame('SideProject', $subreddit->display_name);
        $this->assertSame('https://www.reddit.com/r/SideProject.json', $subreddit->url);
    }

    public function test_duplicate_subreddit_names_are_rejected_case_insensitively(): void
    {
        $admin = $this->adminUser();
        Subreddit::factory()->create(['name' => 'SaaS']);

        $this->actingAs($admin)
            ->from(route('admin.subreddits.create'))
            ->post(route('admin.subreddits.store'), ['name' => 'saas'])
            ->assertRedirect(route('admin.subreddits.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('subreddits', 1);
    }

    public function test_duplicate_subreddit_names_are_rejected_when_editing(): void
    {
        $admin = $this->adminUser();
        Subreddit::factory()->create(['name' => 'SaaS']);
        $other = Subreddit::factory()->create(['name' => 'Startups']);

        $this->actingAs($admin)
            ->put(route('admin.subreddits.update', $other), ['name' => 'saas'])
            ->assertSessionHasErrors('name');

        $this->assertSame('Startups', $other->fresh()->name);
    }

    public function test_unsafe_urls_are_rejected(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from(route('admin.subreddits.create'))
            ->post(route('admin.subreddits.store'), [
                'name' => 'https://evil.example/r/SaaS',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('subreddits', 0);
    }

    public function test_invalid_subreddit_names_are_rejected(): void
    {
        $admin = $this->adminUser();

        $invalidNames = [
            'a',
            str_repeat('a', 22),
            'bad name',
            'bad-name',
            '_leading',
            'trailing_',
        ];

        foreach ($invalidNames as $name) {
            $this->actingAs($admin)
                ->from(route('admin.subreddits.create'))
                ->post(route('admin.subreddits.store'), ['name' => $name])
                ->assertSessionHasErrors('name');
        }

        $this->assertDatabaseCount('subreddits', 0);
    }

    public function test_administrators_can_update_a_subreddit(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->initialCollectionCompleted()->paused()->create([
            'name' => 'OldName',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.subreddits.update', $subreddit), [
            'name' => 'r/NewName',
            'display_name' => '',
            'description' => '',
        ]);

        $subreddit->refresh();

        $response
            ->assertRedirect(route('admin.subreddits.show', $subreddit))
            ->assertSessionHas('success');

        $this->assertSame('NewName', $subreddit->name);
        $this->assertSame('NewName', $subreddit->display_name);
        $this->assertNull($subreddit->description);
        $this->assertSame('https://www.reddit.com/r/NewName.json', $subreddit->url);
        $this->assertSame(SubredditStatus::Paused, $subreddit->status);
        $this->assertSame(InitialCollectionStatus::Completed, $subreddit->initial_collection_status);
        $this->assertNotNull($subreddit->initial_collection_started_at);
        $this->assertNotNull($subreddit->initial_collection_completed_at);
    }

    public function test_administrators_can_pause_and_activate_a_subreddit(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.subreddits.status', $subreddit), ['status' => 'paused'])
            ->assertOk()
            ->assertJsonPath('status', 'paused')
            ->assertJsonPath('label', 'Paused')
            ->assertJsonPath('next_status', 'active');

        $this->assertSame(SubredditStatus::Paused, $subreddit->fresh()->status);

        $this->actingAs($admin)
            ->patchJson(route('admin.subreddits.status', $subreddit), ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('label', 'Active')
            ->assertJsonPath('next_status', 'paused');

        $this->assertSame(SubredditStatus::Active, $subreddit->fresh()->status);
    }

    public function test_invalid_statuses_are_rejected(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create();

        $this->actingAs($admin)
            ->patchJson(route('admin.subreddits.status', $subreddit), ['status' => 'archived'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(SubredditStatus::Active, $subreddit->fresh()->status);
    }

    public function test_non_admin_users_cannot_change_status_or_delete_subreddits(): void
    {
        $user = User::factory()->create();
        $subreddit = Subreddit::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('admin.subreddits.status', $subreddit), ['status' => 'paused'])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson(route('admin.subreddits.destroy', $subreddit))
            ->assertForbidden();

        $this->assertDatabaseHas('subreddits', ['id' => $subreddit->id]);
    }

    public function test_deleting_a_subreddit_removes_its_related_posts(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create();

        $post = RedditPost::create([
            'subreddit_id' => $subreddit->id,
            'reddit_id' => 't3_test123',
            'title' => 'A related post',
        ]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.subreddits.destroy', $subreddit))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('subreddits', ['id' => $subreddit->id]);
        $this->assertDatabaseMissing('reddit_posts', ['id' => $post->id]);
    }

    public function test_deleting_a_subreddit_with_a_standard_form_redirects_to_the_index(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.subreddits.destroy', $subreddit))
            ->assertRedirect(route('admin.subreddits.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('subreddits', ['id' => $subreddit->id]);
    }

    public function test_subreddits_can_be_searched_and_filtered_by_status(): void
    {
        $admin = $this->adminUser();

        Subreddit::factory()->create(['name' => 'SaaS', 'display_name' => 'SaaS Founders']);
        Subreddit::factory()->paused()->create(['name' => 'Entrepreneur', 'display_name' => 'Entrepreneurs']);
        Subreddit::factory()->create(['name' => 'SideProject', 'display_name' => 'Side Projects']);

        $this->actingAs($admin)
            ->get(route('admin.subreddits.index', ['search' => 'SaaS']))
            ->assertOk()
            ->assertSee('r/SaaS')
            ->assertDontSee('r/Entrepreneur')
            ->assertDontSee('r/SideProject');

        $this->actingAs($admin)
            ->get(route('admin.subreddits.index', ['status' => 'paused']))
            ->assertOk()
            ->assertSee('r/Entrepreneur')
            ->assertDontSee('r/SaaS')
            ->assertDontSee('r/SideProject');
    }

    public function test_the_subreddit_index_is_paginated(): void
    {
        $admin = $this->adminUser();
        Subreddit::factory()->count(11)->create();

        $this->actingAs($admin)
            ->get(route('admin.subreddits.index'))
            ->assertOk()
            ->assertViewHas('subreddits', fn ($paginator) => $paginator->total() === 11 && $paginator->count() === 10);

        $this->actingAs($admin)
            ->get(route('admin.subreddits.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('subreddits', fn ($paginator) => $paginator->count() === 1);
    }

    public function test_administrators_can_view_a_subreddit(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create([
            'name' => 'indiehackers',
            'display_name' => 'Indie Hackers',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subreddits.show', $subreddit))
            ->assertOk()
            ->assertSee('r/indiehackers')
            ->assertSee('Indie Hackers')
            ->assertSee($subreddit->url)
            ->assertSee('Initial historical collection is pending');
    }

    public function test_administrators_can_open_the_edit_form(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'startups']);

        $this->actingAs($admin)
            ->get(route('admin.subreddits.edit', $subreddit))
            ->assertOk()
            ->assertSee('r/startups')
            ->assertSee('Save changes');
    }
}
