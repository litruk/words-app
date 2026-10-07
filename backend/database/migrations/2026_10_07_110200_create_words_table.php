<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lookup_id')->nullable()->constrained()->nullOnDelete();

            // What is learnt, and what it is translated into.
            $table->string('language', 8);
            $table->string('translation_language', 8);
            $table->string('term', 160);
            $table->string('kind', 16)->default('word');
            $table->string('part_of_speech', 64)->nullable();
            $table->string('part_of_speech_short', 32)->nullable();
            $table->jsonb('transcriptions')->default('[]');
            $table->jsonb('translations')->default('[]');
            $table->text('note')->nullable();
            $table->text('meaning')->nullable();
            $table->jsonb('examples')->default('[]');
            $table->jsonb('forms')->default('[]');
            $table->jsonb('synonyms')->default('[]');
            $table->string('source', 16)->default('text');

            // Spaced repetition state, see App\Services\Scheduler.
            $table->string('state', 16)->default('new');
            $table->unsignedSmallInteger('step')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->float('interval')->default(0);
            $table->float('ease')->default(2.5);
            $table->unsignedInteger('reps')->default(0);
            $table->unsignedInteger('lapses')->default(0);
            $table->timestamp('last_reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'language', 'due_at']);
            $table->index(['user_id', 'language', 'created_at']);
        });

        // One entry per term and language: adding it again opens the existing one.
        DB::statement('CREATE UNIQUE INDEX words_user_language_term_unique ON words (user_id, language, lower(term))');
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
