<x-layouts.admin title="Add Subreddit">
    <x-breadcrumb :items="[
        ['label' => 'Overview', 'url' => route('admin.dashboard')],
        ['label' => 'Subreddits', 'url' => route('admin.subreddits.index')],
        ['label' => 'Add Subreddit'],
    ]" />

    <x-page-header
        title="Add Subreddit"
        subtitle="Track a new Reddit community and prepare its initial historical collection."
    />

    @include('admin.subreddits._form', ['subreddit' => null])
</x-layouts.admin>
