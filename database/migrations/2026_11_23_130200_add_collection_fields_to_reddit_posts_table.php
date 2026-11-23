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
            $table->boolean('is_self')->default(false);
            $table->decimal('upvote_ratio', 5, 4)->nullable();
            $table->json('raw_data')->nullable();
            $table->string('qualification_status', 20)->default('pending');
            $table->timestamp('qualified_at')->nullable();
            $table->string('comment_collection_status', 20)->default('pending');
            $table->unsignedTinyInteger('comment_collection_attempts')->default(0);
            $table->timestamp('comments_collected_at')->nullable();

            $table->index(
                ['subreddit_id', 'qualification_status', 'comment_collection_status'],
                'posts_qualification_collection_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reddit_posts', function (Blueprint $table) {
            $table->dropIndex('posts_qualification_collection_idx');
            $table->dropColumn([
                'is_self',
                'upvote_ratio',
                'raw_data',
                'qualification_status',
                'qualified_at',
                'comment_collection_status',
                'comment_collection_attempts',
                'comments_collected_at',
            ]);
        });
    }
};
