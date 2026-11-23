<?php

namespace App\Services\Reddit;

use App\Exceptions\RedditCollectionException;
use Carbon\CarbonImmutable;

class RedditCommentParser
{
    /**
     * Parse the comments listing returned by a post comments endpoint.
     *
     * @param  array<int, mixed>  $response
     * @return array{comments: list<array<string, mixed>>, more_ids: list<string>, more_placeholders: int, more_count: int, notes: list<string>}
     */
    public function parseCommentsResponse(array $response, int $maxDepth): array
    {
        $children = $response[1]['data']['children'] ?? null;

        if (! is_array($children)) {
            throw new RedditCollectionException('Reddit returned an unexpected comments listing.');
        }

        return $this->parseChildren($children, $maxDepth);
    }

    /**
     * Parse the "things" list returned by the morechildren endpoint.
     *
     * @param  array<string, mixed>  $response
     * @return array{comments: list<array<string, mixed>>, more_ids: list<string>, more_placeholders: int, more_count: int, notes: list<string>}
     */
    public function parseMoreResponse(array $response, int $maxDepth): array
    {
        $things = $response['json']['data']['things'] ?? null;

        if (! is_array($things)) {
            throw new RedditCollectionException('Reddit returned an unexpected additional-comments listing.');
        }

        return $this->parseChildren($things, $maxDepth);
    }

    /**
     * @param  array<int, mixed>  $children
     * @return array{comments: list<array<string, mixed>>, more_ids: list<string>, more_placeholders: int, more_count: int, notes: list<string>}
     */
    private function parseChildren(array $children, int $maxDepth): array
    {
        $state = [
            'comments' => [],
            'more_ids' => [],
            'more_placeholders' => 0,
            'more_count' => 0,
            'notes' => [],
            'depth_noted' => false,
        ];

        $this->walk($children, 0, $maxDepth, $state);

        return [
            'comments' => array_values($state['comments']),
            'more_ids' => array_values($state['more_ids']),
            'more_placeholders' => $state['more_placeholders'],
            'more_count' => $state['more_count'],
            'notes' => $state['notes'],
        ];
    }

    /**
     * @param  array<int, mixed>  $children
     * @param  array<string, mixed>  $state
     */
    private function walk(array $children, int $depth, int $maxDepth, array &$state): void
    {
        if ($depth > $maxDepth) {
            if (! $state['depth_noted']) {
                $state['notes'][] = 'Comment nesting exceeded the configured depth; deeper replies were skipped.';
                $state['depth_noted'] = true;
            }

            return;
        }

        foreach ($children as $child) {
            if (! is_array($child) || ! is_array($child['data'] ?? null)) {
                continue;
            }

            $kind = $child['kind'] ?? null;
            $data = $child['data'];

            if ($kind === 't1') {
                $comment = $this->comment($data);

                if ($comment !== null) {
                    $state['comments'][$comment['reddit_comment_id']] = $comment;
                }

                $replies = $data['replies'] ?? null;

                if (is_array($replies) && is_array($replies['data']['children'] ?? null)) {
                    $this->walk($replies['data']['children'], $depth + 1, $maxDepth, $state);
                }

                continue;
            }

            if ($kind === 'more') {
                $state['more_placeholders']++;
                $childrenIds = $data['children'] ?? [];

                if (is_array($childrenIds)) {
                    foreach ($childrenIds as $commentId) {
                        $commentId = trim((string) $commentId);

                        if ($commentId !== '') {
                            $state['more_ids'][$commentId] = $commentId;
                        }
                    }

                    $state['more_count'] += isset($data['count'])
                        ? (int) $data['count']
                        : count($childrenIds);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function comment(array $data): ?array
    {
        $commentId = trim((string) ($data['id'] ?? ''));

        if ($commentId === '') {
            return null;
        }

        $createdUtc = null;

        if (isset($data['created_utc']) && is_numeric($data['created_utc'])) {
            $createdUtc = CarbonImmutable::createFromTimestampUTC((int) $data['created_utc'])
                ->setTimezone((string) config('app.timezone'));
        }

        return [
            'reddit_comment_id' => $commentId,
            'parent_id' => isset($data['parent_id']) ? (string) $data['parent_id'] : null,
            'author' => isset($data['author']) ? (string) $data['author'] : null,
            'body' => isset($data['body']) ? (string) $data['body'] : null,
            'score' => (int) ($data['score'] ?? 0),
            'link_id' => isset($data['link_id']) ? (string) $data['link_id'] : null,
            'reddit_created_at' => $createdUtc,
            'raw_data' => $data,
        ];
    }
}
