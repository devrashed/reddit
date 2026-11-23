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
        Schema::create('pain_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reddit_post_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('sentiment', 20)->nullable();
            $table->unsignedInteger('mention_count')->default(1);
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->string('analysis_status', 20)->default('pending');
            $table->timestamps();

            $table->index(['category', 'analysis_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pain_points');
    }
};
