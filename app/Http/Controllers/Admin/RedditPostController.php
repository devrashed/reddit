<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AnalysisConflictException;
use App\Exceptions\DeepSeekException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReanalyzePostRequest;
use App\Http\Requests\Admin\RedditPostIndexRequest;
use App\Models\RedditPost;
use App\Models\Subreddit;
use App\Services\Ai\RedditPostAnalysisService;
use App\Services\Reddit\RedditCommentThreadService;
use App\Services\Reddit\RedditPostQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RedditPostController extends Controller
{
    private const COMMENTS_DISPLAY_LIMIT = 500;

    public function __construct(private readonly RedditPostQueryService $posts) {}

    /**
     * Display the collected Reddit posts.
     */
    public function index(RedditPostIndexRequest $request): View|JsonResponse
    {
        $filters = $request->validated();
        $sort = $filters['sort'] ?? RedditPostQueryService::SORT_NEWEST;
        $posts = $this->posts->paginate($filters, $sort);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('admin.reddit-posts._list', [
                    'posts' => $posts,
                    'filters' => $filters,
                    'sort' => $sort,
                ])->render(),
                'count' => $posts->total(),
            ]);
        }

        return view('admin.reddit-posts.index', [
            'posts' => $posts,
            'filters' => $filters,
            'sort' => $sort,
            'subreddits' => Subreddit::query()
                ->orderBy('name')
                ->get(['id', 'name', 'display_name']),
        ]);
    }

    /**
     * Display a post and its saved threaded comments.
     */
    public function show(RedditPost $redditPost, RedditCommentThreadService $threads): View
    {
        $redditPost->load('subreddit:id,name,display_name')->loadCount('comments');

        $comments = $redditPost->comments()
            ->orderByDesc('score')
            ->orderBy('reddit_created_at')
            ->limit(self::COMMENTS_DISPLAY_LIMIT)
            ->get();

        $latestCompletedVersion = $redditPost->aiAnalyses()
            ->where('analysis_status', 'completed')
            ->latest('version')
            ->value('version');

        return view('admin.reddit-posts.show', [
            'post' => $redditPost,
            'commentThreads' => $threads->build($comments),
            'savedCommentsCount' => $redditPost->comments_count,
            'displayedCommentsCount' => $comments->count(),
            'latestAiAnalysisStatus' => $redditPost->aiAnalyses()->latest('id')->value('analysis_status'),
            'latestCompletedAiVersion' => $latestCompletedVersion,
        ]);
    }

    /**
     * Display a saved analysis report.
     */
    public function analysis(Request $request, RedditPost $redditPost): View
    {
        $request->validate([
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        $redditPost->load('subreddit:id,name,display_name');

        $versions = $redditPost->aiAnalyses()->latest('version')->get();
        $latestCompleted = $versions->firstWhere('analysis_status', 'completed');

        abort_if($latestCompleted === null, 404);

        $requestedVersion = $request->integer('version') ?: $latestCompleted->version;
        $analysis = $versions->firstWhere('version', $requestedVersion);

        abort_if(
            $analysis === null || $analysis->analysis_status !== 'completed',
            404
        );

        $previousCompleted = $versions->first(
            fn ($version): bool => $version->version < $analysis->version
                && $version->analysis_status === 'completed'
        );

        return view('admin.reddit-posts.analysis', [
            'post' => $redditPost,
            'analysis' => $analysis,
            'versions' => $versions,
            'previousCompleted' => $previousCompleted,
        ]);
    }

    /**
     * Run an initial on-demand analysis.
     */
    public function analyze(RedditPost $redditPost, RedditPostAnalysisService $service): JsonResponse
    {
        return $this->runAnalysis($redditPost, false, $service);
    }

    /**
     * Run an explicitly confirmed re-analysis.
     */
    public function reanalyze(
        ReanalyzePostRequest $request,
        RedditPost $redditPost,
        RedditPostAnalysisService $service
    ): JsonResponse {
        return $this->runAnalysis($redditPost, true, $service);
    }

    private function runAnalysis(
        RedditPost $post,
        bool $reanalyze,
        RedditPostAnalysisService $service
    ): JsonResponse {
        try {
            $analysis = $service->analyze($post, $reanalyze);
        } catch (AnalysisConflictException $exception) {
            return response()->json([
                'status' => 'conflict',
                'message' => $exception->getMessage(),
                'analysis_url' => $exception->analysis !== null
                    ? $this->analysisUrl($post, $exception->analysis->version)
                    : null,
            ], 409);
        } catch (DeepSeekException $exception) {
            return response()->json([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ], $exception->statusCode);
        }

        return response()->json([
            'status' => 'completed',
            'analysis_id' => $analysis->getKey(),
            'version' => $analysis->version,
            'provider' => $analysis->provider,
            'model' => $analysis->model,
            'tokens' => [
                'prompt' => $analysis->prompt_tokens,
                'completion' => $analysis->completion_tokens,
                'total' => $analysis->total_tokens,
            ],
            'analysis' => $analysis->analysis_result,
            'analysis_url' => $this->analysisUrl($post, $analysis->version),
        ]);
    }

    private function analysisUrl(RedditPost $post, int $version): string
    {
        return route('admin.reddit-posts.analysis', [
            'redditPost' => $post,
            'version' => $version,
        ]);
    }
}
