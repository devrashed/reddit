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
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reddit_post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('analysis_type', 50);
            $table->string('model', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost', 10, 6)->default(0);
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['analysis_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
