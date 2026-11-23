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
        Schema::create('subreddits', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('subscribers_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_collected_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_collected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subreddits');
    }
};
