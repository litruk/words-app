<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Translations go into this language, and the interface speaks it.
            $table->string('native_language', 8)->default('en')->after('password');
        });

        Schema::create('user_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('language', 8);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_languages');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('native_language');
        });
    }
};
