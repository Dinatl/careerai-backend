<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('original_filename')->nullable();
            $table->longText('cv_text')->nullable();
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->json('suggestions')->nullable();
            $table->timestamps();
        });

        Schema::create('cv_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->longText('job_offer_text');
            $table->unsignedTinyInteger('match_score')->default(0);
            $table->json('matched_skills')->nullable();
            $table->json('missing_skills')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_comparisons');
        Schema::dropIfExists('cv_analyses');
    }
};
