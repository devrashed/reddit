@foreach ($nodes as $node)
    @include('admin.reddit-posts._comment', ['node' => $node])
@endforeach
