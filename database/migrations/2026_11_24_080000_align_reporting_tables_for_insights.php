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
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->index('analyzed_at', 'ai_analyses_analyzed_at_index');
            $table->index(['analysis_status', 'analyzed_at'], 'ai_analyses_status_date_idx');
        });

        Schema::table('pain_points', function (Blueprint $table) {
            $table->renameColumn('reddit_post_id', 'source_post_id');
        });

        Schema::table('pain_points', function (Blueprint $table) {
            $table->foreignId('ai_analysis_id')->nullable()->constrained('ai_analyses')->nullOnDelete();
            $table->text('root_cause')->nullable();
            $table->string('severity', 20)->nullable();
            $table->text('target_audience')->nullable();
            $table->json('evidence')->nullable();
            $table->boolean('is_ai_generated')->default(true);

            $table->unique('ai_analysis_id');
            $table->index('severity', 'pain_points_severity_index');
        });

        Schema::table('business_ideas', function (Blueprint $table) {
            $table->renameColumn('target_audience', 'target_market');
        });

        Schema::table('business_ideas', function (Blueprint $table) {
            $table->foreignId('source_post_id')->nullable()->constrained('reddit_posts')->cascadeOnDelete();
            $table->foreignId('ai_analysis_id')->nullable()->constrained('ai_analyses')->nullOnDelete();
            $table->string('product_type', 100)->nullable();
            $table->json('mvp_features')->nullable();
            $table->json('monetization_ideas')->nullable();
            $table->json('risks')->nullable();
            $table->json('evidence')->nullable();
            $table->boolean('is_ai_generated')->default(true);

            $table->unique('ai_analysis_id');
            $table->index('product_type', 'business_ideas_product_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_ideas', function (Blueprint $table) {
            $table->dropForeign(['source_post_id']);
            $table->dropForeign(['ai_analysis_id']);
            $table->dropUnique('business_ideas_ai_analysis_id_unique');
            $table->dropIndex('business_ideas_product_type_index');
            $table->dropColumn([
                'source_post_id',
                'ai_analysis_id',
                'product_type',
                'mvp_features',
                'monetization_ideas',
                'risks',
                'evidence',
                'is_ai_generated',
            ]);
            $table->renameColumn('target_market', 'target_audience');
        });

        Schema::table('pain_points', function (Blueprint $table) {
            $table->dropForeign(['ai_analysis_id']);
            $table->dropUnique('pain_points_ai_analysis_id_unique');
            $table->dropIndex('pain_points_severity_index');
            $table->dropColumn([
                'ai_analysis_id',
                'root_cause',
                'severity',
                'target_audience',
                'evidence',
                'is_ai_generated',
            ]);
            $table->renameColumn('source_post_id', 'reddit_post_id');
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropIndex('ai_analyses_analyzed_at_index');
            $table->dropIndex('ai_analyses_status_date_idx');
        });
    }
};
