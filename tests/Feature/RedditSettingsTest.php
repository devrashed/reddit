<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reddit\RedditSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class RedditSettingsTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    public function test_administrators_can_view_collection_settings(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Collection schedule')
            ->assertSee('08:00')
            ->assertSee('14:00')
            ->assertSee('20:00')
            ->assertSee('Asia/Dhaka');
    }

    public function test_administrators_can_update_collection_settings(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'regular_enabled' => '1',
                'regular_frequency' => 2,
                'times' => ['18:45', '09:15'],
                'http_timeout' => 20,
                'retry_attempts' => 4,
                'historical_months' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = app(RedditSettings::class);

        $this->assertTrue($settings->regularEnabled());
        $this->assertSame(2, $settings->regularFrequency());
        $this->assertSame(['09:15', '18:45'], $settings->regularTimes());
        $this->assertSame(20, $settings->httpTimeout());
        $this->assertSame(4, $settings->retryAttempts());
        $this->assertSame(3, $settings->historicalMonths());

        $this->assertDatabaseHas('settings', [
            'key' => RedditSettings::KEY_TIMES,
            'type' => 'json',
            'value' => '["09:15","18:45"]',
        ]);
    }

    public function test_administrators_can_disable_scheduled_collection(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'regular_enabled' => '0',
            'regular_frequency' => 3,
            'times' => ['08:00', '14:00', '20:00'],
            'http_timeout' => 10,
            'retry_attempts' => 3,
            'historical_months' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertFalse(app(RedditSettings::class)->regularEnabled());
    }

    public function test_collection_time_count_must_match_the_daily_frequency(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'regular_enabled' => '1',
                'regular_frequency' => 3,
                'times' => ['08:00', '14:00'],
                'http_timeout' => 10,
                'retry_attempts' => 3,
                'historical_months' => 2,
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors('times');
    }

    public function test_collection_times_must_use_a_valid_24_hour_format(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'regular_enabled' => '1',
                'regular_frequency' => 1,
                'times' => ['9am'],
                'http_timeout' => 10,
                'retry_attempts' => 3,
                'historical_months' => 2,
            ])
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors('times.0');
    }

    public function test_non_admin_users_cannot_view_or_update_collection_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.settings.index'))->assertForbidden();

        $this->actingAs($user)
            ->put(route('admin.settings.update'), [
                'regular_enabled' => '1',
                'regular_frequency' => 1,
                'times' => ['08:00'],
                'http_timeout' => 10,
                'retry_attempts' => 3,
                'historical_months' => 2,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('settings', 0);
    }
}
