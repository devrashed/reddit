<x-layouts.admin title="Pain Points">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Pain Points']]" />

    <x-placeholder-page
        icon="bi-bandaid"
        title="Pain point extraction is not active yet"
        description="Customer problems extracted from collected discussions, along with category, sentiment, mention count, and confidence, will be reviewed and curated here."
    />
</x-layouts.admin>
