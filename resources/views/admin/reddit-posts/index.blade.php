<x-layouts.admin title="Reddit Posts">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Reddit Posts']]" />

    <x-placeholder-page
        icon="bi-file-earmark-text"
        title="Post browsing arrives in a later phase"
        description="The collection engine is active and stores fetched submissions with their metrics. A searchable post browser will be added in a later phase."
    />
</x-layouts.admin>
