<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Models\AiAnalysis;
use App\Models\RedditComment;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class RedditPostsDashboardTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    public function test_administrators_can_view_posts_with_all_required_fields(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS', 'display_name' => 'SaaS Founders']);

        $post = $this->createPost($subreddit, 'p1', [
            'title' => 'Churn is killing our SaaS',
            'selftext' => 'We keep losing customers after onboarding.',
            'author' => 'founder',
            'score' => 14,
            'num_comments' => 9,
            'comments_collection_status' => CommentCollectionStatus::Completed,
        ]);

        $this->createAiAnalysis($post, 'completed');
        $this->createComment($post, 'c1', null, 'root', 'A saved comment');

        $response = $this->actingAs($admin)->get(route('admin.reddit-posts.index'));

        $response
            ->assertOk()
            ->assertSee('Churn is killing our SaaS')
            ->assertSee('We keep losing customers after onboarding.')
            ->assertSee('r/SaaS')
            ->assertSee('u/founder')
            ->assertSee('14 score')
            ->assertSee('9 comments')
            ->assertSee('1 saved')
            ->assertSee('Qualified')
            ->assertSee('Comments: Completed')
            ->assertSee('AI: Analyzed')
            ->assertSee('View Analysis')
            ->assertSee('Re-analyze')
            ->assertSee($post->post_url)
            ->assertSee('View post details');

        $response->assertViewHas('posts', function ($paginator): bool {
            $first = $paginator->first();

            return $paginator->total() === 1
                && $first->relationLoaded('subreddit')
                && $first->latest_ai_analysis_status === 'completed';
        });
    }

    public function test_guests_and_non_admins_cannot_access_post_management(): void
    {
        $post = $this->createPost(Subreddit::factory()->create(), 'p1');
        $user = User::factory()->create();

        $this->get(route('admin.reddit-posts.index'))->assertRedirect('/login');
        $this->get(route('admin.reddit-posts.show', $post))->assertRedirect('/login');
        $this->postJson(route('admin.reddit-posts.analyze', $post))->assertUnauthorized();

        $this->actingAs($user)->get(route('admin.reddit-posts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.reddit-posts.show', $post))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.reddit-posts.analyze', $post))->assertForbidden();
    }

    public function test_posts_can_be_filtered_by_search_subreddit_status_metrics_date_and_ai_status(): void
    {
        $admin = $this->adminUser();
        $saas = Subreddit::factory()->create(['name' => 'SaaS']);
        $startups = Subreddit::factory()->create(['name' => 'Startups']);

        $churn = $this->createPost($saas, 'churn', [
            'title' => 'Churn pain',
            'selftext' => 'Customers are leaving.',
            'author' => 'alice',
            'score' => 10,
            'num_comments' => 8,
            'reddit_created_at' => now()->subDay(),
        ]);
        $this->createAiAnalysis($churn, 'completed');

        $this->createPost($startups, 'hiring', [
            'title' => 'Hiring advice',
            'selftext' => 'Recruiting is difficult.',
            'author' => 'bob',
            'score' => 2,
            'num_comments' => 1,
            'reddit_created_at' => now()->subDays(20),
            'qualification_status' => PostQualificationStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['search' => 'Churn']))
            ->assertSee('Churn pain')
            ->assertDontSee('Hiring advice');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['subreddit_id' => $startups->id]))
            ->assertSee('Hiring advice')
            ->assertDontSee('Churn pain');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['qualification_status' => 'qualified']))
            ->assertSee('Churn pain')
            ->assertDontSee('Hiring advice');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['min_score' => 5, 'min_comments' => 5]))
            ->assertSee('Churn pain')
            ->assertDontSee('Hiring advice');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['date_from' => now()->subDays(5)->toDateString()]))
            ->assertSee('Churn pain')
            ->assertDontSee('Hiring advice');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['date_to' => now()->subDays(10)->toDateString()]))
            ->assertSee('Hiring advice')
            ->assertDontSee('Churn pain');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['ai_status' => 'not_analyzed']))
            ->assertSee('Hiring advice')
            ->assertDontSee('Churn pain');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['ai_status' => 'completed']))
            ->assertSee('Churn pain')
            ->assertDontSee('Hiring advice');
    }

    public function test_ai_status_filter_uses_the_latest_analysis_record(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        $post = $this->createPost($subreddit, 'latest', ['title' => 'Latest status post']);

        $this->createAiAnalysis($post, 'completed');
        $this->createAiAnalysis($post, 'processing');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['ai_status' => 'completed']))
            ->assertDontSee('Latest status post');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['ai_status' => 'processing']))
            ->assertSee('Latest status post')
            ->assertSee('AI: In progress')
            ->assertSee('Analysis in progress');
    }

    public function test_ai_states_render_supported_analyze_button_variants(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        $this->createPost($subreddit, 'none', ['title' => 'No analysis post']);
        $this->createAiAnalysis($this->createPost($subreddit, 'pending', ['title' => 'Pending AI post']), 'pending');
        $this->createAiAnalysis($this->createPost($subreddit, 'running', ['title' => 'Running AI post']), 'processing');
        $this->createAiAnalysis($this->createPost($subreddit, 'done', ['title' => 'Completed AI post']), 'completed');
        $this->createAiAnalysis($this->createPost($subreddit, 'failed', ['title' => 'Failed AI post']), 'failed');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index'))
            ->assertOk()
            ->assertSee('AI: Not analyzed')
            ->assertSee('AI: Pending')
            ->assertSee('AI: In progress')
            ->assertSee('AI: Analyzed')
            ->assertSee('AI: Failed')
            ->assertSee('Analysis in progress')
            ->assertSee('View Analysis')
            ->assertSee('Re-analyze')
            ->assertSee('Retry analysis');
    }

    public function test_posts_can_be_sorted_server_side(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        $alpha = $this->createPost($subreddit, 'alpha', [
            'title' => 'Alpha post',
            'score' => 5,
            'num_comments' => 1,
            'reddit_created_at' => now()->subDay(),
        ]);
        $beta = $this->createPost($subreddit, 'beta', [
            'title' => 'Beta post',
            'score' => 50,
            'num_comments' => 2,
            'reddit_created_at' => now()->subDays(3),
        ]);
        $gamma = $this->createPost($subreddit, 'gamma', [
            'title' => 'Gamma post',
            'score' => 10,
            'num_comments' => 30,
            'reddit_created_at' => now()->subDays(2),
        ]);

        DB::table('reddit_posts')->where('id', $alpha->id)->update(['updated_at' => now()->subDays(3)]);
        DB::table('reddit_posts')->where('id', $beta->id)->update(['updated_at' => now()->subDay()]);
        DB::table('reddit_posts')->where('id', $gamma->id)->update(['updated_at' => now()->subDays(2)]);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['sort' => 'newest']))
            ->assertSeeInOrder(['Alpha post', 'Gamma post', 'Beta post']);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['sort' => 'oldest']))
            ->assertSeeInOrder(['Beta post', 'Gamma post', 'Alpha post']);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['sort' => 'highest_score']))
            ->assertSeeInOrder(['Beta post', 'Gamma post', 'Alpha post']);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['sort' => 'most_comments']))
            ->assertSeeInOrder(['Gamma post', 'Beta post', 'Alpha post']);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['sort' => 'recently_updated']))
            ->assertSeeInOrder(['Beta post', 'Gamma post', 'Alpha post']);
    }

    public function test_pagination_is_server_side_and_preserves_filters_and_sorting(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);

        for ($index = 1; $index <= 16; $index++) {
            $this->createPost($subreddit, 'bulk'.$index, [
                'title' => 'Bulk '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'selftext' => 'Needle content '.$index,
                'reddit_created_at' => now()->subDays($index),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['search' => 'Needle']))
            ->assertViewHas('posts', fn ($paginator): bool => $paginator->total() === 16 && $paginator->count() === 15);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', [
                'search' => 'Needle',
                'sort' => 'oldest',
                'page' => 2,
            ]))
            ->assertViewHas('posts', fn ($paginator): bool => $paginator->currentPage() === 2
                && $paginator->count() === 1
                && str_contains($paginator->url(1), 'search=Needle')
                && str_contains($paginator->url(1), 'sort=oldest'));
    }

    public function test_ajax_listing_returns_a_partial_and_validates_filters(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $this->createPost($subreddit, 'ajax', ['title' => 'AJAX visible post']);

        $response = $this->actingAs($admin)->getJson(route('admin.reddit-posts.index', ['search' => 'AJAX']));

        $response
            ->assertOk()
            ->assertJsonStructure(['html', 'count'])
            ->assertJsonPath('count', 1);

        $this->assertStringContainsString('AJAX visible post', $response->json('html'));
        $this->assertStringContainsString('data-analyze-post', $response->json('html'));

        $this->actingAs($admin)
            ->getJson(route('admin.reddit-posts.index', ['sort' => 'unsupported']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort');
    }

    public function test_post_details_show_full_content_threaded_comments_and_escape_html(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $post = $this->createPost($subreddit, 'detail', [
            'title' => 'Detailed post title',
            'selftext' => '<script>alert("x")</script> Full post content.',
            'author' => 'detail_author',
            'score' => 22,
            'num_comments' => 12,
        ]);
        $this->createAiAnalysis($post, 'completed');

        $root = $this->createComment($post, 'root1', 't3_detail', 'root_author', '<b>Root comment</b>', 7);
        $this->createComment($post, 'child1', 't1_root1', 'child_author', 'Child reply', 3);
        $this->createComment($post, 'orphan1', 't1_missing', 'orphan_author', 'Orphan reply', 2);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.show', $post))
            ->assertOk()
            ->assertSee('Detailed post title')
            ->assertSee('Full post content.')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;b&gt;Root comment&lt;/b&gt;', false)
            ->assertDontSee('<b>Root comment</b>', false)
            ->assertSee('r/SaaS')
            ->assertSee('u/detail_author')
            ->assertSee('AI: Analyzed')
            ->assertSee('View Analysis')
            ->assertSee('Re-analyze')
            ->assertSee($post->post_url)
            ->assertSeeInOrder(['root_author', 'child_author'])
            ->assertSee('Orphan reply');

        $this->assertSame('t3_detail', $root->parent_id);
    }

    public function test_post_details_show_an_empty_comments_state(): void
    {
        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'empty-comments');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.show', $post))
            ->assertOk()
            ->assertSee('No saved comments are available for this post yet.');
    }

    public function test_empty_states_are_contextual(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index'))
            ->assertSee('No Reddit posts have been collected yet.');

        $withPosts = Subreddit::factory()->create(['name' => 'SaaS']);
        $emptySubreddit = Subreddit::factory()->create(['name' => 'Startups']);
        $this->createPost($withPosts, 'pending', ['qualification_status' => PostQualificationStatus::Pending]);

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['search' => 'no-such-post']))
            ->assertSee('No posts match your search.');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['subreddit_id' => $emptySubreddit->id]))
            ->assertSee('No posts exist for the selected subreddit.');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.index', ['qualification_status' => 'qualified']))
            ->assertSee('No qualified posts match the current filters.');
    }

    public function test_analyze_endpoint_reports_missing_deepseek_configuration_without_calling_http(): void
    {
        config()->set('deepseek.api_key', null);
        Http::fake();

        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'analyze');

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(422)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'AI analysis is not configured. Add DEEPSEEK_API_KEY to the server environment.');

        $this->assertDatabaseCount('ai_analyses', 0);
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPost(Subreddit $subreddit, string $redditId, array $overrides = []): RedditPost
    {
        return RedditPost::query()->create(array_merge([
            'subreddit_id' => $subreddit->getKey(),
            'reddit_id' => $redditId,
            'title' => 'Post '.$redditId,
            'selftext' => 'Content '.$redditId,
            'author' => 'author_'.$redditId,
            'score' => 10,
            'num_comments' => 6,
            'upvote_ratio' => 0.9,
            'permalink' => "/r/{$subreddit->name}/comments/{$redditId}/post/",
            'post_url' => "https://www.reddit.com/r/{$subreddit->name}/comments/{$redditId}/post/",
            'is_self' => true,
            'reddit_created_at' => now()->subDay(),
            'first_fetched_at' => now()->subDay(),
            'last_fetched_at' => now(),
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Pending,
        ], $overrides));
    }

    private function createComment(
        RedditPost $post,
        string $commentId,
        ?string $parentId,
        string $author,
        string $body,
        int $score = 1
    ): RedditComment {
        return RedditComment::query()->create([
            'post_id' => $post->getKey(),
            'reddit_comment_id' => $commentId,
            'parent_id' => $parentId,
            'author' => $author,
            'body' => $body,
            'score' => $score,
            'reddit_created_at' => now()->subHour(),
            'fetched_at' => now(),
        ]);
    }

    private function createAiAnalysis(RedditPost $post, string $status): AiAnalysis
    {
        return AiAnalysis::query()->create([
            'post_id' => $post->getKey(),
            'analysis_type' => 'pain_point',
            'analysis_status' => $status,
            'version' => ((int) $post->aiAnalyses()->max('version')) + 1,
        ]);
    }
}
