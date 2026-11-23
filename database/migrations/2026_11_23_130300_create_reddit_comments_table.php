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
        Schema::create('reddit_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reddit_post_id')->constrained()->cascadeOnDelete();
            $table->string('reddit_comment_id', 32)->unique();
            $table->string('parent_reddit_id', 32)->nullable();
            $table->string('author')->nullable();
            $table->longText('body')->nullable();
            $table->integer('score')->default(0);
            $table->string('link_id', 32)->nullable();
            $table->timestamp('created_utc')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->index(['reddit_post_id', 'created_utc']);
            $table->index('parent_reddit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reddit_comments');
    }
};
