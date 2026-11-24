<?php

namespace App\Services\Reddit;

use App\Models\RedditComment;
use Illuminate\Support\Collection;

class RedditCommentThreadService
{
    /**
     * Build a nested comment tree from a flat Reddit comment collection.
     *
     * @param  Collection<int, RedditComment>  $comments
     * @return list<array{comment: RedditComment, replies: array}>
     */
    public function build(Collection $comments, int $maxDepth = 20): array
    {
        $byFullName = $comments->keyBy(
            static fn (RedditComment $comment): string => 't1_'.$comment->reddit_comment_id
        );

        $childrenByParent = $comments->groupBy(
            static fn (RedditComment $comment): string => (string) $comment->parent_id
        );

        $roots = $comments->filter(
            static fn (RedditComment $comment): bool => ! $byFullName->has((string) $comment->parent_id)
        );

        $visited = [];

        $buildNode = function (RedditComment $comment, int $depth) use (
            &$buildNode,
            $childrenByParent,
            &$visited,
            $maxDepth
        ): array {
            $fullName = 't1_'.$comment->reddit_comment_id;

            if (isset($visited[$fullName]) || $depth > $maxDepth) {
                return ['comment' => $comment, 'replies' => []];
            }

            $visited[$fullName] = true;

            $replies = $childrenByParent
                ->get($fullName, collect())
                ->map(fn (RedditComment $reply): array => $buildNode($reply, $depth + 1))
                ->values()
                ->all();

            return ['comment' => $comment, 'replies' => $replies];
        };

        return $roots
            ->map(fn (RedditComment $comment): array => $buildNode($comment, 0))
            ->values()
            ->all();
    }
}
