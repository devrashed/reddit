@props(['paginator'])

@if ($paginator->hasPages())
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mt-3">
        <p class="small text-muted mb-0">
            Showing {{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}
            of {{ $paginator->total() }} results
        </p>

        <div>
            {{ $paginator->onEachSide(1)->links() }}
        </div>
    </div>
@endif
