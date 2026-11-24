@php
    $comment = $node['comment'];
@endphp

<div class="comment-node">
    <div class="comment-card">
        <div class="comment-header">
            <span class="fw-semibold">u/{{ $comment->author ?: '[deleted]' }}</span>
            <span class="text-muted small">{{ number_format($comment->score) }} points</span>
            <span class="text-muted small">{{ $comment->reddit_created_at?->format('d M Y, H:i') ?? 'Date unavailable' }}</span>
        </div>

        <div class="comment-body">{{ $comment->body ?: '[Comment content unavailable]' }}</div>
    </div>

    @if (! empty($node['replies']))
        <div class="comment-children">
            @foreach ($node['replies'] as $reply)
                @include('admin.reddit-posts._comment', ['node' => $reply])
            @endforeach
        </div>
    @endif
</div>
