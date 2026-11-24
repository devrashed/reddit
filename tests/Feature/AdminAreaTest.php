<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class AdminAreaTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    public function test_the_root_url_redirects_to_the_admin_area(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_an_administrator_can_visit_every_admin_page(): void
    {
        $admin = $this->adminUser();

        $pages = [
            '/admin' => 'Application overview',
            '/admin/reddit-posts' => 'Reddit Posts',
            '/admin/pain-points' => 'Pain Points',
            '/admin/business-ideas' => 'Business Ideas',
            '/admin/subreddits' => 'Subreddits',
            '/admin/ai-analysis' => 'AI Analysis',
            '/admin/collection-logs' => 'Collection Logs',
            '/admin/settings' => 'Settings',
        ];

        foreach ($pages as $uri => $expectedText) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertOk()
                ->assertSee($expectedText);
        }
    }

    public function test_the_sidebar_exposes_all_navigation_items(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeInOrder([
                'Overview',
                'Reddit Posts',
                'Pain Points',
                'Business Ideas',
                'Subreddits',
                'AI Analysis',
                'Collection Logs',
                'Settings',
            ]);
    }

    public function test_the_system_status_endpoint_returns_a_health_snapshot(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->getJson(route('admin.system.status'))
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('timezone', 'Asia/Dhaka')
            ->assertJsonPath('database.connected', true)
            ->assertJsonStructure([
                'status',
                'application',
                'environment',
                'laravel_version',
                'php_version',
                'timezone',
                'database' => ['driver', 'connected', 'version'],
                'checked_at',
            ]);
    }

    public function test_non_admin_users_cannot_read_the_system_status_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('admin.system.status'))
            ->assertForbidden();
    }

    public function test_the_configured_timezone_is_asia_dhaka(): void
    {
        $this->assertSame('Asia/Dhaka', config('app.timezone'));
        $this->assertSame('Asia/Dhaka', now()->timezoneName);
    }
}
