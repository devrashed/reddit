@php
    $frequency = (int) old('regular_frequency', $values[\App\Services\Reddit\RedditSettings::KEY_FREQUENCY]);
    $times = old('times', $values[\App\Services\Reddit\RedditSettings::KEY_TIMES]);
    $times = array_pad(array_values((array) $times), 6, '');
@endphp

<x-layouts.admin title="Settings">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Settings']]" />

    <x-page-header
        title="Settings"
        subtitle="Configure Reddit collection scheduling, request limits, and historical coverage."
    />

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-heading">
                        <div>
                            <h2>Collection schedule</h2>
                            <small>Regular collection runs only for active subreddits with completed initial collection</small>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="regular_enabled" value="0">
                            <input class="form-check-input"
                                   type="checkbox"
                                   role="switch"
                                   id="regular_enabled"
                                   name="regular_enabled"
                                   value="1"
                                   @checked(old('regular_enabled', $values[\App\Services\Reddit\RedditSettings::KEY_ENABLED]))>
                            <label class="form-check-label fw-semibold" for="regular_enabled">
                                Enable scheduled regular collection
                            </label>
                        </div>
                        <div class="form-text mb-4">
                            When disabled, the scheduler continues running but dispatches no regular collection jobs.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="regular_frequency" class="form-label fw-semibold">
                                    Daily frequency
                                </label>
                                <select id="regular_frequency"
                                        name="regular_frequency"
                                        class="form-select @error('regular_frequency') is-invalid @enderror">
                                    @for ($count = 1; $count <= 6; $count++)
                                        <option value="{{ $count }}" @selected($frequency === $count)>
                                            {{ $count }} {{ $count === 1 ? 'time' : 'times' }} per day
                                        </option>
                                    @endfor
                                </select>
                                @error('regular_frequency')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Collection times ({{ $timezone }})</label>
                                <div class="row g-2">
                                    @for ($index = 0; $index < 6; $index++)
                                        <div class="col-sm-6 col-xl-4 {{ $index >= $frequency ? 'd-none' : '' }}"
                                             data-collection-time-field
                                             data-index="{{ $index }}">
                                            <input type="time"
                                                   name="times[]"
                                                   value="{{ old('times.'.$index, $times[$index]) }}"
                                                   class="form-control @error('times.'.$index) is-invalid @enderror"
                                                   aria-label="Collection time {{ $index + 1 }}"
                                                   @disabled($index >= $frequency)>
                                            @error('times.'.$index)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endfor
                                </div>
                                @error('times')
                                    <div class="text-danger small mt-2">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Provide exactly one time per daily run. The scheduler checks for due times every minute.
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="http_timeout" class="form-label fw-semibold">HTTP timeout</label>
                                <div class="input-group">
                                    <input type="number"
                                           id="http_timeout"
                                           name="http_timeout"
                                           min="5"
                                           max="60"
                                           value="{{ old('http_timeout', $values[\App\Services\Reddit\RedditSettings::KEY_HTTP_TIMEOUT]) }}"
                                           class="form-control @error('http_timeout') is-invalid @enderror">
                                    <span class="input-group-text">seconds</span>
                                    @error('http_timeout')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="retry_attempts" class="form-label fw-semibold">HTTP retry attempts</label>
                                <input type="number"
                                       id="retry_attempts"
                                       name="retry_attempts"
                                       min="1"
                                       max="5"
                                       value="{{ old('retry_attempts', $values[\App\Services\Reddit\RedditSettings::KEY_RETRY_ATTEMPTS]) }}"
                                       class="form-control @error('retry_attempts') is-invalid @enderror">
                                @error('retry_attempts')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="historical_months" class="form-label fw-semibold">Historical period</label>
                                <div class="input-group">
                                    <input type="number"
                                           id="historical_months"
                                           name="historical_months"
                                           min="1"
                                           max="12"
                                           value="{{ old('historical_months', $values[\App\Services\Reddit\RedditSettings::KEY_HISTORICAL_MONTHS]) }}"
                                           class="form-control @error('historical_months') is-invalid @enderror">
                                    <span class="input-group-text">months</span>
                                    @error('historical_months')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-text mt-2">
                            Historical period applies only to a subreddit's one-time initial collection.
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary" data-submit-once>
                        <i class="bi bi-check2 me-1" aria-hidden="true"></i> Save settings
                    </button>
                    <a href="{{ route('admin.collection-logs.index') }}" class="btn btn-light">
                        View collection logs
                    </a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-heading">
                        <h2>Runtime</h2>
                    </div>
                    <div class="card-body">
                        <dl class="system-list row mb-0">
                            <dt class="col-6">Timezone</dt>
                            <dd class="col-6 text-end">{{ $timezone }}</dd>

                            <dt class="col-6">Queue</dt>
                            <dd class="col-6 text-end">{{ $queueConnection }}</dd>

                            <dt class="col-6">Request delay</dt>
                            <dd class="col-6 text-end">{{ config('reddit.request_delay_ms') }} ms</dd>

                            <dt class="col-6">Initial page cap</dt>
                            <dd class="col-6 text-end">{{ config('reddit.initial_max_pages') }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-heading">
                        <div>
                            <h2>Scheduler and queue</h2>
                            <small>Required for background collection</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Run the Laravel scheduler every minute. It dispatches jobs only when a configured
                            collection time is due.
                        </p>
                        <pre class="small bg-light p-2 rounded mb-3 settings-command"><code>* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1</code></pre>

                        <p class="small text-muted">
                            Process the database queue with a persistent worker, or use this cron fallback when a
                            persistent worker is unavailable.
                        </p>
                        <pre class="small bg-light p-2 rounded mb-3 settings-command"><code>* * * * * cd /path-to-app && php artisan queue:work --stop-when-empty --tries=3 --timeout=600 >> /dev/null 2>&1</code></pre>

                        <p class="small text-muted mb-0">
                            Reddit's public endpoints may limit historical coverage. Every collection limitation
                            is recorded in the collection log.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-layouts.admin>
