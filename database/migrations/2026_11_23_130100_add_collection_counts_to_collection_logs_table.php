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
        Schema::table('collection_logs', function (Blueprint $table) {
            $table->string('collection_type', 20)->default('regular');
            $table->unsignedInteger('posts_updated')->default(0);
            $table->unsignedInteger('qualified_posts')->default(0);
            $table->unsignedInteger('comments_fetched')->default(0);
            $table->text('notes')->nullable();

            $table->index(['collection_type', 'status'], 'collection_logs_type_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collection_logs', function (Blueprint $table) {
            $table->dropIndex('collection_logs_type_status_index');
            $table->dropColumn([
                'collection_type',
                'posts_updated',
                'qualified_posts',
                'comments_fetched',
                'notes',
            ]);
        });
    }
};
