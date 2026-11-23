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
        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->timestamp('first_fetched_at')->nullable();
        });

        DB::table('reddit_posts')->update([
            'first_fetched_at' => DB::raw('COALESCE(collected_at, created_at)'),
        ]);

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->renameColumn('body', 'selftext');
            $table->renameColumn('url', 'post_url');
            $table->renameColumn('comments_count', 'num_comments');
            $table->renameColumn('posted_at', 'reddit_created_at');
            $table->renameColumn('collected_at', 'last_fetched_at');
            $table->renameColumn('comment_collection_status', 'comments_collection_status');
        });

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->dropIndex('reddit_posts_subreddit_id_posted_at_index');
            $table->index(['subreddit_id', 'reddit_created_at'], 'reddit_posts_subreddit_created_idx');
            $table->index('score', 'reddit_posts_score_index');
            $table->index('num_comments', 'reddit_posts_num_comments_index');
            $table->index('qualification_status', 'reddit_posts_qualification_status_index');
            $table->index('reddit_created_at', 'reddit_posts_reddit_created_at_index');
        });

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->string('title', 300)->change();
        });

        Schema::table('reddit_comments', function (Blueprint $table) {
            $table->renameColumn('reddit_post_id', 'post_id');
            $table->renameColumn('parent_reddit_id', 'parent_id');
            $table->renameColumn('created_utc', 'reddit_created_at');
            $table->renameColumn('collected_at', 'fetched_at');
        });

        Schema::table('reddit_comments', function (Blueprint $table) {
            $table->index(['post_id', 'reddit_created_at'], 'reddit_comments_post_created_idx');
            $table->index('parent_id', 'reddit_comments_parent_id_index');
        });

        Schema::table('reddit_comments', function (Blueprint $table) {
            $table->dropIndex('reddit_comments_reddit_post_id_created_utc_index');
            $table->dropIndex('reddit_comments_parent_reddit_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reddit_comments', function (Blueprint $table) {
            $table->renameColumn('post_id', 'reddit_post_id');
            $table->renameColumn('parent_id', 'parent_reddit_id');
            $table->renameColumn('reddit_created_at', 'created_utc');
            $table->renameColumn('fetched_at', 'collected_at');
        });

        Schema::table('reddit_comments', function (Blueprint $table) {
            $table->index(['reddit_post_id', 'created_utc']);
            $table->index('parent_reddit_id');
        });

        Schema::table('reddit_comments', function (Blueprint $table) {
            $table->dropIndex('reddit_comments_post_created_idx');
            $table->dropIndex('reddit_comments_parent_id_index');
        });

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->dropIndex('reddit_posts_subreddit_created_idx');
            $table->dropIndex('reddit_posts_score_index');
            $table->dropIndex('reddit_posts_num_comments_index');
            $table->dropIndex('reddit_posts_qualification_status_index');
            $table->dropIndex('reddit_posts_reddit_created_at_index');
            $table->renameColumn('selftext', 'body');
            $table->renameColumn('post_url', 'url');
            $table->renameColumn('num_comments', 'comments_count');
            $table->renameColumn('reddit_created_at', 'posted_at');
            $table->renameColumn('last_fetched_at', 'collected_at');
            $table->renameColumn('comments_collection_status', 'comment_collection_status');
            $table->dropColumn('first_fetched_at');
        });

        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->index(['subreddit_id', 'posted_at']);
        });
    }
};
