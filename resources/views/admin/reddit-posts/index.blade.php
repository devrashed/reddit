<x-layouts.admin title="Reddit Posts">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Reddit Posts']]" />

    <x-placeholder-page
        icon="bi-file-earmark-text"
        title="Reddit post collection is not active yet"
        description="Fetched submissions, scores, comment counts, and source subreddits will be listed here with search, filters, and pagination once collection is enabled."
    />
</x-layouts.admin>
