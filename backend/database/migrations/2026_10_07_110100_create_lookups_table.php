<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dictionary answers from the language model, shared by every user: the
     * same text looked up in the same direction is never paid for twice.
     */
    public function up(): void
    {
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('query', 160);
            $table->string('language', 8);
            $table->string('translation_language', 8);
            // Typed in the translation language and looked up "backwards".
            $table->boolean('reverse')->default(false);
            $table->boolean('found');
            $table->string('term', 160)->nullable();
            $table->jsonb('payload');
            $table->string('model', 64);
            $table->timestamps();

            $table->unique(['query', 'language', 'translation_language', 'reverse']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
