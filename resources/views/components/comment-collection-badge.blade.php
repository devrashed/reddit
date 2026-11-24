@props(['status'])

<span class="badge rounded-pill {{ $status->badgeClass() }}"
      title="Comment collection: {{ $status->label() }}">
    Comments: {{ $status->label() }}
</span>
