@php
    $subreddit = $subreddit ?? null;
    $isEdit = $subreddit !== null;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.subreddits.update', $subreddit) : route('admin.subreddits.store') }}" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-heading">
                    <div>
                        <h2>Subreddit details</h2>
                        <small>The name is stored without the r/ prefix</small>
                    </div>
                </div>

                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">
                            Subreddit name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">r/</span>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   value="{{ old('name', $subreddit?->name) }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="smallbusiness"
                                   data-subreddit-name-input
                                   autocomplete="off"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">
                            Use letters, numbers, and underscores. A pasted r/name or Reddit URL is normalized
                            automatically.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="display_name" class="form-label fw-semibold">
                            Display name <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <input type="text"
                               id="display_name"
                               name="display_name"
                               value="{{ old('display_name', $subreddit?->display_name) }}"
                               class="form-control @error('display_name') is-invalid @enderror"
                               maxlength="100"
                               placeholder="Human friendly label">
                        @error('display_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Defaults to the subreddit name when left empty.</div>
                    </div>

                    <div>
                        <label for="description" class="form-label fw-semibold">
                            Description <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <textarea id="description"
                                  name="description"
                                  rows="4"
                                  maxlength="1000"
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Why is this community useful for discovery?">{{ old('description', $subreddit?->description) }}</textarea>
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-heading">
                    <div>
                        <h2>Generated URL</h2>
                        <small>Read only</small>
                    </div>
                </div>
                <div class="card-body">
                    <p class="small text-muted">
                        The Reddit JSON endpoint is generated from the subreddit name. Arbitrary URLs are never
                        accepted.
                    </p>
                    <code id="subredditUrlPreview"
                          class="d-block text-break p-2 bg-light rounded"
                          data-subreddit-url-preview>{{ $subreddit?->url ?? 'https://www.reddit.com/r/subreddit.json' }}</code>
                </div>
            </div>

            @if ($isEdit)
                <div class="card mb-3">
                    <div class="card-heading">
                        <h2>Current state</h2>
                    </div>
                    <div class="card-body">
                        <dl class="system-list row mb-0">
                            <dt class="col-6">Status</dt>
                            <dd class="col-6 text-end">
                                <x-subreddit-status-badge :status="$subreddit->status" />
                            </dd>

                            <dt class="col-6">Initial collection</dt>
                            <dd class="col-6 text-end">
                                <x-initial-collection-badge :status="$subreddit->initial_collection_status" />
                            </dd>

                            <dt class="col-6">Created</dt>
                            <dd class="col-6 text-end">{{ $subreddit->created_at?->format('d M Y') }}</dd>
                        </dl>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        New subreddits start as <strong>Active</strong>.
                    </p>
                    <p class="small text-muted mb-0">
                        Their initial historical collection is marked <strong>Pending</strong>. Collection
                        processing will be implemented in Phase 3.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-primary" data-submit-once>
            <i class="bi bi-check2 me-1" aria-hidden="true"></i>
            {{ $isEdit ? 'Save changes' : 'Add subreddit' }}
        </button>
        <a href="{{ $isEdit ? route('admin.subreddits.show', $subreddit) : route('admin.subreddits.index') }}" class="btn btn-light">
            Cancel
        </a>
    </div>
</form>
