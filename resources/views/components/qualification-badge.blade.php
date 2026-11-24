@props(['status'])

<span class="badge rounded-pill {{ $status->badgeClass() }}"
      title="Qualification status: {{ $status->label() }}">
    {{ $status->label() }}
</span>
