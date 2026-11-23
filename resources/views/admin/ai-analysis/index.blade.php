<x-layouts.admin title="AI Analysis">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'AI Analysis']]" />

    <x-placeholder-page
        icon="bi-cpu"
        title="AI analysis workspace arrives in Phase 2"
        description="DeepSeek analysis runs, token usage, estimated cost, processing status, and structured results will be inspectable from this page."
    />
</x-layouts.admin>
