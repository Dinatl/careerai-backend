<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('job_title');
            $table->unsignedTinyInteger('overall_score')->default(0);
            $table->timestamps();
        });

        Schema::create('interview_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('interview_sessions')->onDelete('cascade');
            $table->text('question');
            $table->longText('user_answer')->nullable();
            $table->longText('ai_feedback')->nullable();
            $table->unsignedTinyInteger('star_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_questions');
        Schema::dropIfExists('interview_sessions');
    }
};
