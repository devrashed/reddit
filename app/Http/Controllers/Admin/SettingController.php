<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRedditSettingsRequest;
use App\Services\Ai\AiSettings;
use App\Services\Reddit\RedditSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        private readonly RedditSettings $settings,
        private readonly AiSettings $aiSettings
    ) {}

    /**
     * Display the collection and AI settings.
     */
    public function index(): View
    {
        $commonTimezones = [
            'Asia/Dhaka',
            'UTC',
            'Asia/Kolkata',
            'Asia/Dubai',
            'Asia/Singapore',
            'Asia/Tokyo',
            'Europe/London',
            'Europe/Berlin',
            'America/New_York',
            'America/Chicago',
            'America/Los_Angeles',
            'Australia/Sydney',
        ];

        $selectedTimezone = $this->settings->timezone();

        if (! in_array($selectedTimezone, $commonTimezones, true)) {
            $commonTimezones[] = $selectedTimezone;
        }

        return view('admin.settings.index', [
            'values' => $this->settings->all(),
            'aiValues' => $this->aiSettings->all(),
            'timezones' => $commonTimezones,
            'timezone' => $selectedTimezone,
            'queueConnection' => config('queue.default'),
            'apiKeyConfigured' => filled(config('deepseek.api_key')),
        ]);
    }

    /**
     * Persist collection and AI settings.
     */
    public function update(UpdateRedditSettingsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $this->settings->save($request->validated());
            $this->aiSettings->save($request->validated());
        });

        return back()->with('success', 'Application settings saved.');
    }
}
