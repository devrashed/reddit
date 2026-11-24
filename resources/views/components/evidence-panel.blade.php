@props(['evidence' => []])

@php
    $postEvidence = (array) data_get($evidence, 'post', []);
    $commentEvidence = (array) data_get($evidence, 'comments', []);
    $classification = (array) data_get($evidence, 'classification', []);
    $classificationLabels = [
        'supported_facts' => 'Directly Supported Facts',
        'ai_interpretations' => 'AI-Generated Interpretations',
        'hypotheses_to_validate' => 'Hypotheses Requiring Validation',
    ];
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <div class="evidence-box h-100">
            <h3 class="h6">Evidence from the Reddit post</h3>
            @if ($postEvidence === [])
                <p class="small text-muted mb-0">No post evidence was returned.</p>
            @else
                <ul class="small mb-0">
                    @foreach ($postEvidence as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <div class="evidence-box h-100">
            <h3 class="h6">Evidence from saved comments</h3>
            @if ($commentEvidence === [])
                <p class="small text-muted mb-0">No comment evidence was returned.</p>
            @else
                <ul class="small mb-0">
                    @foreach ($commentEvidence as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @foreach ($classificationLabels as $key => $label)
        @php
            $items = (array) data_get($classification, $key, []);
        @endphp

        <div class="col-md-4">
            <div class="evidence-box h-100">
                <h3 class="h6">{{ $label }}</h3>
                @if ($items === [])
                    <p class="small text-muted mb-0">None identified.</p>
                @else
                    <ul class="small mb-0">
                        @foreach ($items as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endforeach
</div>

<div class="alert alert-light border mt-3 mb-0">
    <div class="d-flex gap-2">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <div class="small">
            AI-generated findings are hypotheses, not validated demand. Independent market and customer
            validation is required.
        </div>
    </div>
</div>
