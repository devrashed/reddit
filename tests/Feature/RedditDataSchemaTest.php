<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Models\AiAnalysis;
use App\Models\RedditComment;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Services\Reddit\RedditPostSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\FakesRedditCollection;
use Tests\TestCase;

class RedditDataSchemaTest extends TestCase
{
    use FakesRedditCollection;
    use RefreshDatabase;

    public function test_posts_and_comments_use_the_canonical_phase_four_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('reddit_posts', [
            'id',
            'reddit_id',
            'subreddit_id',
            'title',
            'selftext',
            'author',
            'score',
            'num_comments',
            'upvote_ratio',
            'permalink',
            'post_url',
            'is_self',
            'reddit_created_at',
            'first_fetched_at',
            'last_fetched_at',
            'qualification_status',
            'comments_collection_status',
            'comments_collected_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('reddit_comments', [
            'id',
            'reddit_comment_id',
            'post_id',
            'parent_id',
            'author',
            'body',
            'score',
            'reddit_created_at',
            'fetched_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertFalse(Schema::hasColumn('reddit_posts', 'body'));
        $this->assertFalse(Schema::hasColumn('reddit_posts', 'comments_count'));
        $this->assertFalse(Schema::hasColumn('reddit_posts', 'posted_at'));
        $this->assertFalse(Schema::hasColumn('reddit_posts', 'analysis_status'));

        $postIndexes = collect(Schema::getIndexes('reddit_posts'))->pluck('name');
        $commentIndexes = collect(Schema::getIndexes('reddit_comments'))->pluck('name');

        $this->assertContains('reddit_posts_reddit_id_unique', $postIndexes);
        $this->assertContains('reddit_posts_subreddit_created_idx', $postIndexes);
        $this->assertContains('reddit_posts_score_index', $postIndexes);
        $this->assertContains('reddit_posts_num_comments_index', $postIndexes);
        $this->assertContains('reddit_posts_qualification_status_index', $postIndexes);
        $this->assertContains('reddit_posts_reddit_created_at_index', $postIndexes);

        $this->assertContains('reddit_comments_reddit_comment_id_unique', $commentIndexes);
        $this->assertContains('reddit_comments_post_created_idx', $commentIndexes);
        $this->assertContains('reddit_comments_parent_id_index', $commentIndexes);
    }

    public function test_posts_are_upserted_and_fetch_timestamps_and_local_state_are_preserved(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-23 10:00:00', 'Asia/Dhaka'));

        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $service = app(RedditPostSyncService::class);
        $child = $this->postChild('p1', [
            'title' => 'First title',
            'score' => 7,
            'num_comments' => 3,
        ]);

        $first = $service->sync($subreddit, [$child]);

        $this->assertSame(1, $first['inserted']);
        $this->assertSame(0, $first['updated']);

        $post = RedditPost::query()->where('reddit_id', 'p1')->sole();
        $this->assertSame('First title', $post->title);
        $this->assertSame('Body p1', $post->selftext);
        $this->assertSame(7, $post->score);
        $this->assertSame(3, $post->num_comments);
        $this->assertSame('https://www.reddit.com/r/SaaS/comments/p1/post_p1/', $post->post_url);
        $this->assertNotNull($post->reddit_created_at);
        $this->assertNotNull($post->first_fetched_at);
        $this->assertNotNull($post->last_fetched_at);

        $post->forceFill([
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Completed,
            'comments_collected_at' => now(),
        ])->save();

        $firstFetchedAt = $post->first_fetched_at;
        $createdAt = $post->created_at;

        Carbon::setTestNow(now()->addMinutes(5));

        $second = $service->sync($subreddit, [
            $this->postChild('p1', [
                'title' => 'Updated title',
                'score' => 25,
                'num_comments' => 9,
            ]),
        ]);

        $post->refresh();

        $this->assertSame(0, $second['inserted']);
        $this->assertSame(1, $second['updated']);
        $this->assertDatabaseCount('reddit_posts', 1);
        $this->assertSame('Updated title', $post->title);
        $this->assertSame(25, $post->score);
        $this->assertSame(9, $post->num_comments);
        $this->assertTrue($firstFetchedAt->equalTo($post->first_fetched_at));
        $this->assertTrue($post->last_fetched_at->greaterThan($firstFetchedAt));
        $this->assertTrue($createdAt->equalTo($post->created_at));
        $this->assertSame(PostQualificationStatus::Qualified, $post->qualification_status);
        $this->assertSame(CommentCollectionStatus::Completed, $post->comments_collection_status);
    }

    public function test_comments_are_inserted_without_duplicates_and_keep_the_original_created_timestamp(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $post = $this->createPost($subreddit, 'p1');

        $createdAt = now()->subHour();
        $fetchOne = now()->subMinute();

        $this->upsertComment([
            'post_id' => $post->getKey(),
            'reddit_comment_id' => 'c1',
            'parent_id' => 't3_p1',
            'author' => 'author',
            'body' => 'Original comment',
            'score' => 2,
            'link_id' => 't3_p1',
            'reddit_created_at' => $createdAt,
            'fetched_at' => $fetchOne,
        ]);

        $comment = RedditComment::query()->where('reddit_comment_id', 'c1')->sole();
        $dbCreatedAt = $comment->created_at;

        $this->upsertComment([
            'post_id' => $post->getKey(),
            'reddit_comment_id' => 'c1',
            'parent_id' => 't3_p1',
            'author' => 'author',
            'body' => 'Updated comment body',
            'score' => 12,
            'link_id' => 't3_p1',
            'reddit_created_at' => $createdAt,
            'fetched_at' => now(),
        ]);

        $comment->refresh();

        $this->assertDatabaseCount('reddit_comments', 1);
        $this->assertSame('Updated comment body', $comment->body);
        $this->assertSame(12, $comment->score);
        $this->assertTrue($dbCreatedAt->equalTo($comment->created_at));
        $this->assertTrue($comment->redditPost->is($post));
        $this->assertSame(1, $post->comments()->count());
    }

    public function test_relationships_and_eager_loading_work_across_posts_comments_and_ai_analyses(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $first = $this->createPost($subreddit, 'p1');
        $second = $this->createPost($subreddit, 'p2');

        RedditComment::query()->create([
            'post_id' => $first->getKey(),
            'reddit_comment_id' => 'r1',
            'parent_id' => 't3_p1',
            'author' => 'commenter',
            'body' => 'Comment',
            'score' => 3,
            'reddit_created_at' => now()->subHour(),
            'fetched_at' => now(),
        ]);

        AiAnalysis::query()->create([
            'reddit_post_id' => $second->getKey(),
            'analysis_type' => 'pain_point',
            'status' => 'completed',
        ]);

        $loaded = Subreddit::query()
            ->with(['posts.subreddit', 'posts.comments', 'posts.aiAnalyses'])
            ->findOrFail($subreddit->getKey());

        $this->assertCount(2, $loaded->posts);
        $this->assertSame(1, $loaded->posts->firstWhere('id', $first->getKey())->comments->count());
        $this->assertSame(1, $loaded->posts->firstWhere('id', $second->getKey())->aiAnalyses->count());
        $this->assertTrue($loaded->posts->first()->subreddit->is($subreddit));

        $aiPost = $loaded->posts->firstWhere('id', $second->getKey());
        $this->assertSame(CommentCollectionStatus::Pending, $aiPost->comments_collection_status);
        $this->assertSame('completed', $aiPost->aiAnalyses->first()->status);
    }

    public function test_foreign_key_constraints_remove_dependent_comments_and_ai_analyses(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $post = $this->createPost($subreddit, 'p1');

        RedditComment::query()->create([
            'post_id' => $post->getKey(),
            'reddit_comment_id' => 'fk1',
            'body' => 'Comment',
            'fetched_at' => now(),
        ]);

        AiAnalysis::query()->create([
            'reddit_post_id' => $post->getKey(),
            'analysis_type' => 'pain_point',
            'status' => 'pending',
        ]);

        $post->delete();

        $this->assertDatabaseMissing('reddit_comments', ['reddit_comment_id' => 'fk1']);
        $this->assertDatabaseMissing('ai_analyses', ['reddit_post_id' => $post->getKey()]);

        $remainingPost = $this->createPost($subreddit, 'p2');
        $subreddit->delete();

        $this->assertDatabaseMissing('reddit_posts', ['id' => $remainingPost->getKey()]);
    }

    public function test_transaction_rollback_does_not_leave_partial_posts(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $service = app(RedditPostSyncService::class);

        DB::beginTransaction();
        $service->sync($subreddit, [$this->postChild('tx1')]);
        $this->assertDatabaseCount('reddit_posts', 1);
        DB::rollBack();

        $this->assertDatabaseCount('reddit_posts', 0);
    }

    public function test_large_post_batches_are_chunked_and_upserted_safely(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $service = app(RedditPostSyncService::class);

        $children = [];

        for ($index = 1; $index <= 250; $index++) {
            $children[] = $this->postChild('bulk'.$index, ['score' => $index]);
        }

        $inserted = $service->sync($subreddit, $children);

        $this->assertSame(250, $inserted['fetched']);
        $this->assertSame(250, $inserted['inserted']);
        $this->assertDatabaseCount('reddit_posts', 250);

        $updatedChildren = array_map(function (array $child): array {
            $child['data']['score'] = 999;

            return $child;
        }, $children);

        $updated = $service->sync($subreddit, $updatedChildren);

        $this->assertSame(0, $updated['inserted']);
        $this->assertSame(250, $updated['updated']);
        $this->assertDatabaseCount('reddit_posts', 250);
        $this->assertSame(250, RedditPost::query()->where('score', 999)->count());
    }

    public function test_missing_posts_are_not_deleted_by_a_later_listing(): void
    {
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $service = app(RedditPostSyncService::class);

        $service->sync($subreddit, [$this->postChild('keep1'), $this->postChild('keep2')]);

        $keepTwo = RedditPost::query()->where('reddit_id', 'keep2')->sole();
        RedditComment::query()->create([
            'post_id' => $keepTwo->getKey(),
            'reddit_comment_id' => 'keep-comment',
            'body' => 'Preserve me',
            'fetched_at' => now(),
        ]);

        $service->sync($subreddit, [$this->postChild('keep1', ['score' => 50])]);

        $this->assertDatabaseCount('reddit_posts', 2);
        $this->assertDatabaseHas('reddit_posts', ['reddit_id' => 'keep2']);
        $this->assertDatabaseHas('reddit_posts', ['reddit_id' => 'keep1', 'score' => 50]);
        $this->assertDatabaseHas('reddit_comments', ['reddit_comment_id' => 'keep-comment']);
    }

    private function createPost(Subreddit $subreddit, string $redditId): RedditPost
    {
        return RedditPost::query()->create([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => $redditId,
            'title' => 'Post '.$redditId,
            'selftext' => 'Body '.$redditId,
            'author' => 'author',
            'score' => 1,
            'num_comments' => 1,
            'permalink' => "/r/SaaS/comments/{$redditId}/post/",
            'post_url' => "https://www.reddit.com/r/SaaS/comments/{$redditId}/post/",
            'is_self' => true,
            'reddit_created_at' => now()->subDay(),
            'first_fetched_at' => now(),
            'last_fetched_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertComment(array $attributes): void
    {
        RedditComment::query()->upsert(
            [$attributes + ['raw_data' => json_encode(['id' => $attributes['reddit_comment_id']])]],
            ['reddit_comment_id'],
            ['parent_id', 'author', 'body', 'score', 'link_id', 'reddit_created_at', 'raw_data', 'fetched_at']
        );
    }
}
