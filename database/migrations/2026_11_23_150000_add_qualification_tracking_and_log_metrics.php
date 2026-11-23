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
        DB::table('reddit_posts')
            ->where('qualification_status', 'rejected')
            ->update(['qualification_status' => 'pending']);

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->timestamp('pending_rechecked_at')->nullable();
            $table->index(
                ['subreddit_id', 'qualification_status', 'pending_rechecked_at'],
                'posts_pending_recheck_idx'
            );
        });

        Schema::table('collection_logs', function (Blueprint $table) {
            $table->unsignedInteger('pending_posts_checked')->default(0);
            $table->unsignedInteger('newly_qualified_posts')->default(0);
            $table->unsignedInteger('failed_operations')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_logs', function (Blueprint $table) {
            $table->dropColumn([
                'pending_posts_checked',
                'newly_qualified_posts',
                'failed_operations',
            ]);
        });

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->dropIndex('posts_pending_recheck_idx');
            $table->dropColumn('pending_rechecked_at');
        });
    }
};
