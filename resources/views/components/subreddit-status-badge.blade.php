@props(['status'])

<span class="badge rounded-pill {{ $status->badgeClass() }}" data-subreddit-status-badge>
    {{ $status->label() }}
</span>
