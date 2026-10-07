<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every answer given in a study session: how many new cards were already
     * introduced today, whether the user has studied today, session stats.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->string('language', 8);
            $table->unsignedTinyInteger('rating');
            $table->string('state_before', 16);
            $table->float('interval_before');
            $table->float('interval_after');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('reviewed_at');

            $table->index(['user_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
