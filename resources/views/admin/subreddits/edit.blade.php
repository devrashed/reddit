<x-layouts.admin :title="'Edit r/'.$subreddit->name">
    <x-breadcrumb :items="[
        ['label' => 'Overview', 'url' => route('admin.dashboard')],
        ['label' => 'Subreddits', 'url' => route('admin.subreddits.index')],
        ['label' => 'r/'.$subreddit->name, 'url' => route('admin.subreddits.show', $subreddit)],
        ['label' => 'Edit'],
    ]" />

    <x-page-header
        :title="'Edit r/'.$subreddit->name"
        subtitle="Update community details. Status changes are managed from the subreddit page."
    >
        <x-slot:actions>
            <x-subreddit-status-badge :status="$subreddit->status" />
            <a href="{{ route('admin.subreddits.show', $subreddit) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye me-1" aria-hidden="true"></i> View subreddit
            </a>
        </x-slot:actions>
    </x-page-header>

    @include('admin.subreddits._form', ['subreddit' => $subreddit])
</x-layouts.admin>
