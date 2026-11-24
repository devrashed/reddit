<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('ai_analyses')
            ->where('status', 'running')
            ->update(['status' => 'processing']);

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->renameColumn('reddit_post_id', 'post_id');
            $table->renameColumn('result', 'analysis_result');
            $table->renameColumn('status', 'analysis_status');
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->string('provider', 50)->default('deepseek');
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('analyzed_at')->nullable();
        });

        $postIds = DB::table('ai_analyses')
            ->whereNotNull('post_id')
            ->distinct()
            ->orderBy('post_id')
            ->pluck('post_id');

        foreach ($postIds as $postId) {
            $ids = DB::table('ai_analyses')
                ->where('post_id', $postId)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $index => $analysisId) {
                DB::table('ai_analyses')
                    ->where('id', $analysisId)
                    ->update(['version' => $index + 1]);
            }
        }

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->unsignedInteger('prompt_tokens')->nullable()->default(null)->change();
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->unsignedInteger('completion_tokens')->nullable()->default(null)->change();
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->unique(['post_id', 'version'], 'ai_analyses_post_version_unique');
            $table->index(['post_id', 'analysis_status', 'id'], 'ai_analyses_post_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_analyses')
            ->where('analysis_status', 'processing')
            ->update(['analysis_status' => 'running']);

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropUnique('ai_analyses_post_version_unique');
            $table->dropIndex('ai_analyses_post_status_idx');
        });

        DB::table('ai_analyses')->whereNull('prompt_tokens')->update(['prompt_tokens' => 0]);
        DB::table('ai_analyses')->whereNull('completion_tokens')->update(['completion_tokens' => 0]);

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->unsignedInteger('prompt_tokens')->default(0)->change();
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->unsignedInteger('completion_tokens')->default(0)->change();
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->renameColumn('post_id', 'reddit_post_id');
            $table->renameColumn('analysis_result', 'result');
            $table->renameColumn('analysis_status', 'status');
            $table->dropColumn(['provider', 'total_tokens', 'version', 'analyzed_at']);
        });
    }
};
