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
        Schema::table('subreddits', function (Blueprint $table) {
            $table->string('url', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->string('initial_collection_status', 20)->default('pending');
            $table->timestamp('initial_collection_started_at')->nullable();
            $table->timestamp('initial_collection_completed_at')->nullable();
        });

        DB::table('subreddits')->where('is_active', true)->update(['status' => 'active']);
        DB::table('subreddits')->where('is_active', false)->update(['status' => 'paused']);

        DB::table('subreddits')->chunkById(100, function ($subreddits): void {
            foreach ($subreddits as $subreddit) {
                DB::table('subreddits')
                    ->where('id', $subreddit->id)
                    ->update([
                        'url' => 'https://www.reddit.com/r/'.rawurlencode($subreddit->name).'.json',
                    ]);
            }
        });

        Schema::table('subreddits', function (Blueprint $table) {
            $table->unique('url');
            $table->index(['status', 'last_collected_at']);
            $table->index('initial_collection_status');
        });

        Schema::table('subreddits', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'last_collected_at']);
            $table->dropColumn('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subreddits', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        DB::table('subreddits')->where('status', 'active')->update(['is_active' => true]);
        DB::table('subreddits')->where('status', 'paused')->update(['is_active' => false]);

        Schema::table('subreddits', function (Blueprint $table) {
            $table->dropUnique(['url']);
            $table->dropIndex(['status', 'last_collected_at']);
            $table->dropIndex(['initial_collection_status']);
            $table->dropColumn([
                'url',
                'status',
                'initial_collection_status',
                'initial_collection_started_at',
                'initial_collection_completed_at',
            ]);
        });

        Schema::table('subreddits', function (Blueprint $table) {
            $table->index(['is_active', 'last_collected_at']);
        });
    }
};
