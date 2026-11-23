<?php

use App\Http\Controllers\Admin\AiAnalysisController;
use App\Http\Controllers\Admin\BusinessIdeaController;
use App\Http\Controllers\Admin\CollectionLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PainPointController;
use App\Http\Controllers\Admin\RedditPostController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubredditController;
use App\Http\Controllers\Admin\SystemStatusController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('reddit-posts', [RedditPostController::class, 'index'])->name('reddit-posts.index');
        Route::get('pain-points', [PainPointController::class, 'index'])->name('pain-points.index');
        Route::get('business-ideas', [BusinessIdeaController::class, 'index'])->name('business-ideas.index');

        Route::get('subreddits', [SubredditController::class, 'index'])->name('subreddits.index');
        Route::get('subreddits/create', [SubredditController::class, 'create'])->name('subreddits.create');
        Route::post('subreddits', [SubredditController::class, 'store'])->name('subreddits.store');
        Route::get('subreddits/{subreddit}', [SubredditController::class, 'show'])->name('subreddits.show');
        Route::get('subreddits/{subreddit}/edit', [SubredditController::class, 'edit'])->name('subreddits.edit');
        Route::put('subreddits/{subreddit}', [SubredditController::class, 'update'])->name('subreddits.update');
        Route::patch('subreddits/{subreddit}/status', [SubredditController::class, 'updateStatus'])->name('subreddits.status');
        Route::delete('subreddits/{subreddit}', [SubredditController::class, 'destroy'])->name('subreddits.destroy');

        Route::get('ai-analysis', [AiAnalysisController::class, 'index'])->name('ai-analysis.index');
        Route::get('collection-logs', [CollectionLogController::class, 'index'])->name('collection-logs.index');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('system/status', SystemStatusController::class)->name('system.status');
    });
