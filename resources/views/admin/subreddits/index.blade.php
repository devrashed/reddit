<x-layouts.admin title="Subreddits">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Subreddits']]" />

    <x-placeholder-page
        icon="bi-collection"
        title="Subreddit management arrives in Phase 2"
        description="This page will manage tracked communities, collection status, subscriber counts, and the last successful collection time for each source."
    />
</x-layouts.admin>
