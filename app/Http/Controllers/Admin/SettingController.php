<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRedditSettingsRequest;
use App\Services\Reddit\RedditSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(private readonly RedditSettings $settings) {}

    /**
     * Display the Reddit collection settings.
     */
    public function index(): View
    {
        return view('admin.settings.index', [
            'values' => $this->settings->all(),
            'timezone' => config('app.timezone'),
            'queueConnection' => config('queue.default'),
        ]);
    }

    /**
     * Persist the Reddit collection settings.
     */
    public function update(UpdateRedditSettingsRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated());

        return back()->with('success', 'Reddit collection settings saved.');
    }
}
