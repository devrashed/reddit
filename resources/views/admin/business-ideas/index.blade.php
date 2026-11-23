<x-layouts.admin title="Business Ideas">
    <x-breadcrumb :items="[['label' => 'Overview', 'url' => route('admin.dashboard')], ['label' => 'Business Ideas']]" />

    <x-placeholder-page
        icon="bi-lightbulb"
        title="Idea generation is not active yet"
        description="Business opportunities derived from validated pain points, including market and feasibility scores, target audience, and status, will be managed from this page."
    />
</x-layouts.admin>
