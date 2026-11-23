<x-layouts.admin title="Collection Logs">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Collection Logs']]" />

    <x-placeholder-page
        icon="bi-clock-history"
        title="Collection history is not active yet"
        description="Every Reddit collection run will be recorded here with its source subreddit, status, fetched and created counts, duration, and error details."
    />
</x-layouts.admin>
