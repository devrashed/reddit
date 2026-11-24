<?php

namespace Tests\Feature;

use App\Enums\CommentCollectionStatus;
use App\Enums\PostQualificationStatus;
use App\Models\AiAnalysis;
use App\Models\BusinessIdea;
use App\Models\PainPoint;
use App\Models\RedditComment;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Models\User;
use App\Services\Ai\AnalysisInsightPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Tests\Concerns\InteractsWithAdmins;
use Tests\TestCase;

class DeepSeekAnalysisTest extends TestCase
{
    use InteractsWithAdmins;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'deepseek.api_key' => 'test-secret-key',
            'deepseek.base_url' => 'https://api.deepseek.test',
            'deepseek.path' => '/chat/completions',
            'deepseek.model' => 'deepseek-chat',
            'deepseek.timeout' => 5,
            'deepseek.retry_attempts' => 2,
            'deepseek.retry_base_delay_ms' => 10,
            'deepseek.comments_limit' => 10,
            'deepseek.json_mode' => true,
        ]);

        Sleep::fake();
        Http::preventStrayRequests();
    }

    public function test_administrator_can_run_an_on_demand_analysis_and_view_the_saved_report(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $post = $this->createPost($subreddit, 'analysis1', [
            'title' => 'How do we reduce churn?',
            'selftext' => 'Customers leave after onboarding. We need a practical fix.',
        ]);
        $this->createComment($post, 'c1', 't3_analysis1', 'alice', 'Onboarding emails did not help us.');
        $this->createComment($post, 'c2', 't1_c1', 'bob', 'A short checklist fixed this for us.');

        $unrelated = $this->createPost($subreddit, 'unrelated', ['title' => 'Unrelated post title']);
        $this->createComment($unrelated, 'u1', 't3_unrelated', 'eve', 'Unrelated comment body.');

        $this->fakeSuccessfulDeepSeek();

        $response = $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('provider', 'deepseek')
            ->assertJsonPath('model', 'deepseek-chat')
            ->assertJsonPath('tokens.prompt', 100)
            ->assertJsonPath('tokens.completion', 200)
            ->assertJsonPath('tokens.total', 300);

        $analysis = AiAnalysis::query()->sole();

        $this->assertSame($post->getKey(), $analysis->post_id);
        $this->assertSame('deepseek', $analysis->provider);
        $this->assertSame('deepseek-chat', $analysis->model);
        $this->assertSame('completed', $analysis->analysis_status);
        $this->assertSame(1, $analysis->version);
        $this->assertSame(100, $analysis->prompt_tokens);
        $this->assertSame(200, $analysis->completion_tokens);
        $this->assertSame(300, $analysis->total_tokens);
        $this->assertNotNull($analysis->analyzed_at);
        $this->assertNull($analysis->error_message);
        $this->assertArrayHasKey('problem_identification', $analysis->analysis_result);

        $painPoint = PainPoint::query()->sole();
        $idea = BusinessIdea::query()->sole();

        $this->assertSame($post->getKey(), $painPoint->source_post_id);
        $this->assertSame($analysis->getKey(), $painPoint->ai_analysis_id);
        $this->assertTrue($painPoint->is_ai_generated);
        $this->assertSame('Churn after onboarding.', $painPoint->title);
        $this->assertSame('The activation path is unclear.', $painPoint->root_cause);
        $this->assertSame('A lightweight onboarding assistant could help.', $idea->title);
        $this->assertSame($painPoint->getKey(), $idea->pain_point_id);
        $this->assertSame($post->getKey(), $idea->source_post_id);
        $this->assertSame($analysis->getKey(), $idea->ai_analysis_id);
        $this->assertSame('SaaS onboarding checklist.', $idea->product_type);
        $this->assertSame(['Checklist templates', 'Progress tracking'], $idea->mvp_features);
        $this->assertTrue($idea->is_ai_generated);
        $this->assertNotEmpty($idea->evidence['classification']['supported_facts']);

        $this->assertStringNotContainsString('test-secret-key', $response->getContent());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($post): bool {
            $body = json_decode($request->body(), true);
            $messages = json_encode($body['messages'] ?? [], JSON_UNESCAPED_SLASHES);

            return $request->url() === 'https://api.deepseek.test/chat/completions'
                && in_array('Bearer test-secret-key', $request->header('Authorization'), true)
                && ($body['model'] ?? null) === 'deepseek-chat'
                && ($body['response_format']['type'] ?? null) === 'json_object'
                && str_contains($messages, $post->title)
                && str_contains($messages, 'Onboarding emails did not help us.')
                && str_contains($messages, 't1_c1')
                && ! str_contains($messages, 'Unrelated post title')
                && ! str_contains($messages, 'Unrelated comment body.')
                && str_contains($messages, 'market size')
                && str_contains($messages, 'single Reddit post');
        });

        $analysisUrl = $response->json('analysis_url');

        $this->actingAs($admin)
            ->get($analysisUrl)
            ->assertOk()
            ->assertSee('Problem Identification')
            ->assertSee('Evidence from Saved Comments')
            ->assertSee('Directly Supported Facts')
            ->assertSee('deepseek-chat')
            ->assertSee('Version 1')
            ->assertDontSee('test-secret-key');

        Http::assertSentCount(1);

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(409)
            ->assertJsonPath('status', 'conflict');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_analyses', 1);
    }

    public function test_reanalysis_requires_confirmation_and_creates_a_new_version(): void
    {
        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'reanalyze');
        $this->createComment($post, 'c1', 't3_reanalyze', 'alice', 'Saved evidence.');

        $this->fakeSuccessfulDeepSeek();

        $first = $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertOk()
            ->assertJsonPath('version', 1);

        $firstAnalysis = AiAnalysis::query()->where('version', 1)->sole();
        $firstResult = $firstAnalysis->analysis_result;

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.reanalyze', $post))
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirm');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_analyses', 1);

        $second = $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.reanalyze', $post), ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('version', 2);

        Http::assertSentCount(2);
        $this->assertDatabaseCount('ai_analyses', 2);
        $this->assertDatabaseHas('ai_analyses', ['version' => 1, 'analysis_status' => 'completed']);
        $this->assertDatabaseHas('ai_analyses', ['version' => 2, 'analysis_status' => 'completed']);
        $this->assertDatabaseCount('pain_points', 2);
        $this->assertDatabaseCount('business_ideas', 2);

        $this->assertSame($firstResult, $firstAnalysis->fresh()->analysis_result);

        $this->actingAs($admin)
            ->get($second->json('analysis_url'))
            ->assertOk()
            ->assertSee('Version 2')
            ->assertSee('Version 1')
            ->assertSee('Version history');

        Http::assertSentCount(2);
    }

    public function test_token_usage_is_null_when_the_provider_does_not_return_it(): void
    {
        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'no-tokens');

        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response(
                $this->validApiPayload(null),
                200
            ),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertOk();

        $analysis = AiAnalysis::query()->sole();

        $this->assertNull($analysis->prompt_tokens);
        $this->assertNull($analysis->completion_tokens);
        $this->assertNull($analysis->total_tokens);

        $this->actingAs($admin)
            ->get($response->json('analysis_url'))
            ->assertOk()
            ->assertSee('Not provided');
    }

    public function test_failed_analysis_is_saved_and_can_be_explicitly_retried(): void
    {
        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'retry');
        $calls = 0;

        Http::fake([
            'https://api.deepseek.test/chat/completions' => function () use (&$calls) {
                $calls++;

                return $calls <= 2
                    ? Http::response(['error' => 'server'], 500)
                    : Http::response($this->validApiPayload(), 200);
            },
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(502)
            ->assertJsonPath('status', 'failed');

        $failed = AiAnalysis::query()->where('version', 1)->sole();
        $this->assertSame('failed', $failed->analysis_status);
        $this->assertStringContainsString('temporarily unavailable', (string) $failed->error_message);
        $this->assertStringNotContainsString('test-secret-key', (string) $failed->error_message);
        $this->assertNull($failed->analysis_result);
        Http::assertSentCount(2);

        $retry = $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertOk()
            ->assertJsonPath('version', 2);

        Http::assertSentCount(3);
        $this->assertDatabaseHas('ai_analyses', ['version' => 1, 'analysis_status' => 'failed']);
        $this->assertDatabaseHas('ai_analyses', ['version' => 2, 'analysis_status' => 'completed']);

        $this->actingAs($admin)
            ->get($retry->json('analysis_url'))
            ->assertOk()
            ->assertSee('Version 1')
            ->assertSee('Version 2')
            ->assertSee('Failed');
    }

    public function test_invalid_json_and_incomplete_reports_fail_without_leaking_secrets(): void
    {
        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'invalid');

        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::sequence()
                ->push($this->apiPayload('not valid json'), 200)
                ->push($this->apiPayload(json_encode(['problem_identification' => 'only one key'])), 200),
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(502)
            ->assertJsonPath('status', 'failed');

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(502)
            ->assertJsonPath('status', 'failed');

        $this->assertDatabaseCount('ai_analyses', 2);
        $this->assertSame(2, AiAnalysis::query()->where('analysis_status', 'failed')->count());

        foreach (AiAnalysis::query()->get() as $analysis) {
            $this->assertStringNotContainsString('test-secret-key', (string) $analysis->error_message);
        }
    }

    public function test_api_authentication_failure_is_saved_without_retrying_or_exposing_the_key(): void
    {
        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'unauthorized');

        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(422)
            ->assertJsonPath('status', 'failed');

        $this->assertStringContainsString('credentials', $response->json('message'));
        $this->assertStringNotContainsString('test-secret-key', $response->getContent());

        $analysis = AiAnalysis::query()->sole();
        $this->assertSame('failed', $analysis->analysis_status);
        $this->assertStringContainsString('credentials', (string) $analysis->error_message);
        Http::assertSentCount(1);
    }

    public function test_duplicate_analysis_requests_are_prevented_by_lock_and_status(): void
    {
        $admin = $this->adminUser();
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'concurrent');

        Http::fake();

        $lock = Cache::lock('deepseek-analysis:'.$post->getKey(), 60);
        $this->assertTrue($lock->get());

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(409)
            ->assertJsonPath('status', 'conflict');

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_analyses', 0);

        $lock->release();

        AiAnalysis::query()->create([
            'post_id' => $post->getKey(),
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'analysis_type' => 'business_opportunity',
            'analysis_status' => 'processing',
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.reddit-posts.analyze', $post))
            ->assertStatus(409)
            ->assertJsonPath('status', 'conflict');

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_analyses', 1);
    }

    public function test_dashboard_and_post_pages_never_trigger_deepseek(): void
    {
        $admin = $this->adminUser();
        $subreddit = Subreddit::factory()->create(['name' => 'SaaS']);
        $post = $this->createPost($subreddit, 'no-auto');
        $this->createComment($post, 'c1', 't3_no-auto', 'alice', 'No automatic AI please.');

        Http::fake();

        $this->actingAs($admin)->get(route('admin.reddit-posts.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reddit-posts.show', $post))->assertOk();
        $this->actingAs($admin)->get(route('admin.reddit-posts.analysis', $post))->assertNotFound();

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_analyses', 0);
    }

    public function test_analysis_schema_tracks_versions_tokens_and_statuses(): void
    {
        $this->assertTrue(Schema::hasColumns('ai_analyses', [
            'id',
            'post_id',
            'provider',
            'model',
            'analysis_result',
            'prompt_tokens',
            'completion_tokens',
            'total_tokens',
            'analysis_status',
            'version',
            'analyzed_at',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes('ai_analyses'))->pluck('name');

        $this->assertContains('ai_analyses_post_version_unique', $indexes);
        $this->assertContains('ai_analyses_post_status_idx', $indexes);
    }

    public function test_ai_report_routes_are_protected(): void
    {
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'auth');
        $analysis = AiAnalysis::query()->create([
            'post_id' => $post->getKey(),
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'analysis_type' => 'business_opportunity',
            'analysis_status' => 'completed',
            'analysis_result' => $this->validResult(),
            'version' => 1,
            'analyzed_at' => now(),
        ]);

        $user = User::factory()->create();

        $this->get(route('admin.reddit-posts.analysis', $post))->assertRedirect('/login');
        $this->postJson(route('admin.reddit-posts.reanalyze', $post), ['confirm' => true])->assertUnauthorized();

        $this->actingAs($user)->get(route('admin.reddit-posts.analysis', $post))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.reddit-posts.reanalyze', $post), ['confirm' => true])->assertForbidden();

        $this->actingAs($this->adminUser())->get(route('admin.reddit-posts.analysis', $post))->assertOk();
        $this->assertSame('completed', $analysis->fresh()->analysis_status);
    }

    public function test_insight_persistence_is_idempotent_for_the_same_analysis_version(): void
    {
        $post = $this->createPost(Subreddit::factory()->create(['name' => 'SaaS']), 'idempotent');
        $analysis = AiAnalysis::query()->create([
            'post_id' => $post->getKey(),
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'analysis_type' => 'business_opportunity',
            'analysis_status' => 'completed',
            'analysis_result' => $this->validResult(),
            'version' => 1,
            'analyzed_at' => now(),
        ]);

        $persister = app(AnalysisInsightPersister::class);
        $persister->persist($analysis);
        $persister->persist($analysis->fresh());

        $this->assertDatabaseCount('pain_points', 1);
        $this->assertDatabaseCount('business_ideas', 1);
        $this->assertSame($analysis->getKey(), PainPoint::query()->sole()->ai_analysis_id);
        $this->assertSame($analysis->getKey(), BusinessIdea::query()->sole()->ai_analysis_id);
    }

    private function fakeSuccessfulDeepSeek(): void
    {
        Http::fake([
            'https://api.deepseek.test/chat/completions' => Http::response($this->validApiPayload(), 200),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $usage
     * @return array<string, mixed>
     */
    private function validApiPayload(?array $usage = ['prompt_tokens' => 100, 'completion_tokens' => 200, 'total_tokens' => 300]): array
    {
        $payload = $this->apiPayload(json_encode($this->validResult(), JSON_THROW_ON_ERROR));

        if ($usage !== null) {
            $payload['usage'] = $usage;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function apiPayload(string $content): array
    {
        return [
            'id' => 'chatcmpl-test',
            'model' => 'deepseek-chat',
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $content],
                    'finish_reason' => 'stop',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validResult(): array
    {
        return [
            'problem_identification' => 'Customers struggle with the first-week onboarding step.',
            'main_pain_point' => 'Churn after onboarding.',
            'root_cause' => 'The activation path is unclear.',
            'current_workaround' => 'Manual emails and a spreadsheet checklist.',
            'existing_solution_limitations' => 'The workaround is hard to maintain.',
            'solution_gap' => 'A focused onboarding checklist is missing.',
            'business_opportunity' => 'A lightweight onboarding assistant could help.',
            'suggested_product_type' => 'SaaS onboarding checklist.',
            'target_audience' => 'Small SaaS teams.',
            'suggested_mvp_features' => ['Checklist templates', 'Progress tracking'],
            'monetization_ideas' => ['Monthly subscription'],
            'risks_and_limitations' => ['One Reddit post is not proof of demand.'],
            'evidence_from_post' => ['The post reports churn after onboarding.'],
            'evidence_from_comments' => ['Comment t1_c1 says onboarding emails did not help.'],
            'evidence_classification' => [
                'supported_facts' => ['Onboarding is mentioned as a problem.'],
                'ai_interpretations' => ['A guided checklist may improve activation.'],
                'hypotheses_to_validate' => ['Teams would pay for a dedicated tool.'],
            ],
        ];
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

    private function createComment(
        RedditPost $post,
        string $commentId,
        ?string $parentId,
        string $author,
        string $body
    ): RedditComment {
        return RedditComment::query()->create([
            'post_id' => $post->getKey(),
            'reddit_comment_id' => $commentId,
            'parent_id' => $parentId,
            'author' => $author,
            'body' => $body,
            'score' => 3,
            'reddit_created_at' => now()->subHour(),
            'fetched_at' => now(),
        ]);
    }
}
