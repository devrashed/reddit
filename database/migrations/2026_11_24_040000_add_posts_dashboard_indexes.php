<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->index('updated_at', 'reddit_posts_updated_at_index');
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->index(['reddit_post_id', 'id'], 'ai_analyses_post_latest_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropIndex('ai_analyses_post_latest_idx');
        });

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->dropIndex('reddit_posts_updated_at_index');
        });
    }
};
