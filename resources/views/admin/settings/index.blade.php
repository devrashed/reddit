<x-layouts.admin title="Settings">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Settings']]" />

    <x-placeholder-page
        icon="bi-gear"
        title="Settings management arrives in Phase 2"
        description="Collection preferences, AI provider configuration, notification options, and other application settings will be managed securely from this page."
    />
</x-layouts.admin>
