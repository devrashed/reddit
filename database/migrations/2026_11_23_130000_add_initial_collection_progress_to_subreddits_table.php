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
        Schema::table('subreddits', function (Blueprint $table) {
            $table->string('initial_collection_cursor', 100)->nullable();
            $table->unsignedInteger('initial_collection_pages')->default(0);
            $table->unsignedInteger('initial_collection_posts_fetched')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subreddits', function (Blueprint $table) {
            $table->dropColumn([
                'initial_collection_cursor',
                'initial_collection_pages',
                'initial_collection_posts_fetched',
            ]);
        });
    }
};
