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
        Schema::create('business_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pain_point_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('target_audience')->nullable();
            $table->longText('proposed_solution')->nullable();
            $table->unsignedTinyInteger('market_score')->nullable();
            $table->unsignedTinyInteger('feasibility_score')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['status', 'overall_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_ideas');
    }
};
