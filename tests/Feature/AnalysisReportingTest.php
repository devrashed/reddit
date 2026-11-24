<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Enums\SubredditStatus;
use App\Models\AiAnalysis;
use App\Models\BusinessIdea;
use App\Models\PainPoint;
use App\Models\RedditComment;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Models\User;
use App\Services\Ai\AnalysisInsightPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class AnalysisReportingTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    public function test_ai_analysis_versions_can_be_searched_filtered_and_opened_without_ai_calls(): void
    {
        Http::fake();

        $admin = $this->adminUser();
        $saas = Subreddit::factory()->create(['name' => 'SaaS']);
        $startups = Subreddit::factory()->create(['name' => 'Startups']);

        $churnPost = $this->createPost($saas, 'churn', ['title' => 'Churn discovery post']);
        $churn = $this->createAnalysis($churnPost, 'completed', 1, [
            'prompt_tokens' => 120,
            'completion_tokens' => 180,
            'total_tokens' => 300,
        ]);

        $pricingPost = $this->createPost($startups, 'pricing', ['title' => 'Pricing discovery post']);
        $this->createAnalysis($pricingPost, 'failed', 1, ['error_message' => 'Provider failed']);

        $oldPost = $this->createPost($saas, 'old', ['title' => 'Old insight post']);
        $oldAnalysis = $this->createAnalysis($oldPost, 'completed', 1);
        DB::table('ai_analyses')->where('id', $oldAnalysis->id)->update([
            'created_at' => now()->subDays(3),
            'analyzed_at' => now()->subDays(3),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.ai-analysis.index'))
            ->assertOk()
            ->assertSee('Churn discovery post')
            ->assertSee('Pricing discovery post')
            ->assertSee('View Report')
            ->assertSee('deepseek-chat');

        $this->actingAs($admin)
            ->get(route('admin.ai-analysis.index', ['search' => 'Churn']))
            ->assertSee('Churn discovery post')
            ->assertDontSee('Pricing discovery post');

        $this->actingAs($admin)
            ->get(route('admin.ai-analysis.index', ['subreddit_id' => $startups->id]))
            ->assertSee('Pricing discovery post')
            ->assertDontSee('Churn discovery post');

        $this->actingAs($admin)
            ->get(route('admin.ai-analysis.index', ['status' => 'completed']))
            ->assertSee('Churn discovery post')
            ->assertDontSee('Pricing discovery post');

        $this->actingAs($admin)
            ->get(route('admin.ai-analysis.index', ['date_from' => now()->toDateString()]))
            ->assertSee('Churn discovery post')
            ->assertDontSee('Old insight post');

        $this->actingAs($admin)
            ->get(route('admin.reddit-posts.analysis', ['redditPost' => $churnPost, 'version' => 1]))
            ->assertOk()
            ->assertSee('Business opportunity report')
            ->assertSee('Version 1');

        Http::assertNothingSent();
        $this->assertSame(3, AiAnalysis::query()->count());
    }

    public function test_analysis_report_page_compares_previous_versions(): void
    {
        Http::fake();

        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'compare');
        $this->createAnalysis($post, 'completed', 1, [
            'analysis_result' => $this->validResult([
                'problem_identification' => 'Old problem statement.',
                'main_pain_point' => 'Old pain point.',
            ]),
        ]);
        $this->createAnalysis($post, 'completed', 2, [
            'analysis_result' => $this->validResult([
                'problem_identification' => 'New problem statement.',
                'main_pain_point' => 'New pain point.',
            ]),
        ]);

        $this->actingAs($this->adminUser())
            ->get(route('admin.reddit-posts.analysis', ['redditPost' => $post, 'version' => 2]))
            ->assertOk()
            ->assertSee('Compare versions')
            ->assertSee('Old problem statement.')
            ->assertSee('New problem statement.')
            ->assertSee('Version 1')
            ->assertSee('Version 2');

        Http::assertNothingSent();
    }

    public function test_pain_points_are_searchable_filterable_traceable_and_marked_as_ai_generated(): void
    {
        Http::fake();

        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $post = $this->createPost($subreddit, 'pain', ['title' => 'Onboarding pain source']);
        $analysis = $this->createAnalysis($post, 'completed', 1);
        app(AnalysisInsightPersister::class)->persist($analysis);

        $painPoint = PainPoint::query()->sole();

        $this->actingAs($admin)
            ->get(route('admin.pain-points.index'))
            ->assertOk()
            ->assertSee($painPoint->title)
            ->assertSee('AI-generated')
            ->assertSee('r/SaaS')
            ->assertSee('Not assessed');

        $this->actingAs($admin)
            ->get(route('admin.pain-points.index', ['search' => $painPoint->title]))
            ->assertSee($painPoint->title);

        $this->actingAs($admin)
            ->get(route('admin.pain-points.index', ['subreddit_id' => $subreddit->id]))
            ->assertSee($painPoint->title);

        $this->actingAs($admin)
            ->get(route('admin.pain-points.index', ['severity' => 'not_assessed']))
            ->assertSee($painPoint->title);

        $this->actingAs($admin)
            ->get(route('admin.pain-points.show', $painPoint))
            ->assertOk()
            ->assertSee($painPoint->title)
            ->assertSee('AI-generated')
            ->assertSee($painPoint->root_cause)
            ->assertSee('Evidence from the Reddit post')
            ->assertSee('Directly Supported Facts')
            ->assertSee('Onboarding pain source')
            ->assertSee(BusinessIdea::query()->sole()->title);

        Http::assertNothingSent();
    }

    public function test_business_ideas_are_searchable_filterable_and_source_linked(): void
    {
        Http::fake();

        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $post = $this->createPost($subreddit, 'idea', [
            'title' => 'Idea source post',
            'post_url' => 'https://www.reddit.com/r/SaaS/comments/idea/post/',
        ]);
        $analysis = $this->createAnalysis($post, 'completed', 1);
        app(AnalysisInsightPersister::class)->persist($analysis);

        $idea = BusinessIdea::query()->sole();
        $painPoint = PainPoint::query()->sole();

        $this->actingAs($admin)
            ->get(route('admin.business-ideas.index'))
            ->assertOk()
            ->assertSee($idea->title)
            ->assertSee('AI-generated')
            ->assertSee($idea->product_type)
            ->assertSee($painPoint->title);

        $this->actingAs($admin)
            ->get(route('admin.business-ideas.index', ['search' => $idea->product_type]))
            ->assertSee($idea->title);

        $this->actingAs($admin)
            ->get(route('admin.business-ideas.index', ['subreddit_id' => $subreddit->id]))
            ->assertSee($idea->title);

        $this->actingAs($admin)
            ->get(route('admin.business-ideas.index', ['status' => 'draft']))
            ->assertSee($idea->title);

        $this->actingAs($admin)
            ->get(route('admin.business-ideas.show', $idea))
            ->assertOk()
            ->assertSee($idea->title)
            ->assertSee('AI-generated')
            ->assertSee('Checklist templates')
            ->assertSee('Monthly subscription')
            ->assertSee('One Reddit post is not proof of demand.')
            ->assertSee('Evidence from saved comments')
            ->assertSee($post->post_url)
            ->assertSee($painPoint->title);

        Http::assertNothingSent();
    }

    public function test_dashboard_uses_aggregate_counts_and_token_totals(): void
    {
        Http::fake();

        $active = Subreddit::factory()->create(['name' => 'SaaS', 'status' => SubredditStatus::Active]);
        Subreddit::factory()->paused()->create(['name' => 'Paused']);

        $qualifiedPending = $this->createPost($active, 'q-pending', [
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Pending,
        ]);
        $qualifiedDone = $this->createPost($active, 'q-done', [
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Completed,
        ]);
        $this->createPost($active, 'pending-post', [
            'qualification_status' => PostQualificationStatus::Pending,
            'comments_collection_status' => CommentCollectionStatus::Pending,
        ]);

        RedditComment::query()->create([
            'post_id' => $qualifiedDone->getKey(),
            'reddit_comment_id' => 'dash-c1',
            'body' => 'Comment',
            'fetched_at' => now(),
        ]);
        RedditComment::query()->create([
            'post_id' => $qualifiedDone->getKey(),
            'reddit_comment_id' => 'dash-c2',
            'body' => 'Second comment',
            'fetched_at' => now(),
        ]);

        $completed = $this->createAnalysis($qualifiedDone, 'completed', 1, [
            'total_tokens' => 300,
            'prompt_tokens' => 100,
            'completion_tokens' => 200,
        ]);
        $this->createAnalysis($qualifiedPending, 'failed', 1, ['total_tokens' => null]);
        app(AnalysisInsightPersister::class)->persist($completed);

        $this->actingAs($this->adminUser())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Subreddits')
            ->assertSee('DeepSeek Tokens')
            ->assertViewHas('metrics', function (array $metrics): bool {
                $values = collect($metrics)->pluck('value', 'label');

                return $values['Total Subreddits'] === 2
                    && $values['Active Subreddits'] === 1
                    && $values['Total Reddit Posts'] === 3
                    && $values['Qualified Posts'] === 2
                    && $values['Awaiting Comments'] === 1
                    && $values['Collected Comments'] === 2
                    && $values['AI Analyses'] === 2
                    && $values['Pain Points'] === 1
                    && $values['Business Ideas'] === 1
                    && $values['DeepSeek Tokens'] === 300;
            });

        Http::assertNothingSent();
    }

    public function test_reporting_routes_are_protected_and_do_not_call_ai(): void
    {
        Http::fake();

        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'protected');
        $analysis = $this->createAnalysis($post, 'completed', 1);
        app(AnalysisInsightPersister::class)->persist($analysis);
        $painPoint = PainPoint::query()->sole();
        $idea = BusinessIdea::query()->sole();
        $user = User::factory()->create();

        $this->get(route('admin.ai-analysis.index'))->assertRedirect('/login');
        $this->get(route('admin.pain-points.index'))->assertRedirect('/login');
        $this->get(route('admin.business-ideas.index'))->assertRedirect('/login');

        $this->actingAs($user)->get(route('admin.ai-analysis.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.pain-points.show', $painPoint))->assertForbidden();
        $this->actingAs($user)->get(route('admin.business-ideas.show', $idea))->assertForbidden();

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
            'num_comments' => 5,
            'permalink' => "/r/{$subreddit->name}/comments/{$redditId}/post/",
            'post_url' => "https://www.reddit.com/r/{$subreddit->name}/comments/{$redditId}/post/",
            'is_self' => true,
            'reddit_created_at' => now()->subDay(),
            'first_fetched_at' => now()->subDay(),
            'last_fetched_at' => now(),
            'qualification_status' => PostQualificationStatus::Qualified,
            'comments_collection_status' => CommentCollectionStatus::Completed,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAnalysis(
        RedditPost $post,
        string $status,
        int $version,
        array $overrides = []
    ): AiAnalysis {
        return AiAnalysis::query()->create(array_merge([
            'post_id' => $post->getKey(),
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'analysis_type' => 'business_opportunity',
            'analysis_result' => $status === 'completed' ? $this->validResult() : null,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'total_tokens' => null,
            'analysis_status' => $status,
            'version' => $version,
            'analyzed_at' => $status === 'completed' ? now() : null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validResult(array $overrides = []): array
    {
        return array_merge([
            'problem_identification' => 'The post describes churn after onboarding.',
            'main_pain_point' => 'Churn after onboarding.',
            'root_cause' => 'The activation path is unclear.',
            'current_workaround' => 'Manual emails and spreadsheets.',
            'existing_solution_limitations' => 'Manual work is hard to maintain.',
            'solution_gap' => 'No focused checklist product.',
            'business_opportunity' => 'A lightweight onboarding assistant.',
            'suggested_product_type' => 'SaaS onboarding checklist.',
            'target_audience' => 'Small SaaS teams.',
            'suggested_mvp_features' => ['Checklist templates', 'Progress tracking'],
            'monetization_ideas' => ['Monthly subscription'],
            'risks_and_limitations' => ['One Reddit post is not proof of demand.'],
            'evidence_from_post' => ['The post reports churn after onboarding.'],
            'evidence_from_comments' => ['Comment t1_c1 mentions onboarding emails.'],
            'evidence_classification' => [
                'supported_facts' => ['Onboarding is mentioned as a problem.'],
                'ai_interpretations' => ['A guided checklist may improve activation.'],
                'hypotheses_to_validate' => ['Teams would pay for a dedicated tool.'],
            ],
        ], $overrides);
    }
}
