@props(['status'])

<span class="badge rounded-pill {{ $status->badgeClass() }}">
    {{ $status->label() }}
</span>
