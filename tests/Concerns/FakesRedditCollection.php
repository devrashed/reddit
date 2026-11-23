<?php

namespace Tests\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

trait FakesRedditCollection
{
    protected function prepareRedditCollectionFakes(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
    }

    /**
     * @param  list<array<string, mixed>>  $posts
     * @return array<string, mixed>
     */
    protected function listingResponse(array $posts, ?string $after = null): array
    {
        return [
            'kind' => 'Listing',
            'data' => [
                'after' => $after,
                'children' => array_map(
                    static fn (array $post): array => ['kind' => 't3', 'data' => $post],
                    $posts
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function postData(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => 'Post '.$id,
            'selftext' => 'Body '.$id,
            'author' => 'author_'.$id,
            'score' => 5,
            'num_comments' => 3,
            'permalink' => "/r/SaaS/comments/{$id}/post_{$id}/",
            'url' => "https://www.reddit.com/r/SaaS/comments/{$id}/post_{$id}/",
            'created_utc' => now()->subDay()->timestamp,
            'is_self' => true,
            'upvote_ratio' => 0.92,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<array<string, mixed>>  $replies
     * @return array<string, mixed>
     */
    protected function commentData(string $id, array $overrides = [], array $replies = []): array
    {
        $data = array_merge([
            'id' => $id,
            'parent_id' => 't3_postid',
            'author' => 'commenter_'.$id,
            'body' => 'Comment '.$id,
            'score' => 2,
            'created_utc' => now()->subHours(2)->timestamp,
            'link_id' => 't3_postid',
        ], $overrides);

        $data['replies'] = $replies === []
            ? ''
            : ['kind' => 'Listing', 'data' => ['children' => $replies]];

        return ['kind' => 't1', 'data' => $data];
    }

    /**
     * @param  list<string>  $children
     * @return array<string, mixed>
     */
    protected function moreData(array $children, int $count): array
    {
        return [
            'kind' => 'more',
            'data' => [
                'id' => 'more_'.implode('_', array_slice($children, 0, 3)),
                'children' => $children,
                'count' => $count,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<int, mixed>
     */
    protected function commentsResponse(array $children): array
    {
        return [
            ['kind' => 'Listing', 'data' => ['children' => []]],
            ['kind' => 'Listing', 'data' => ['children' => $children]],
        ];
    }

    public function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
