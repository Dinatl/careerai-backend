<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('riasec_type')->nullable();
            $table->json('steps');
            $table->timestamps();
        });

        Schema::create('roadmap_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('roadmap_id')->nullable()->constrained('roadmaps')->nullOnDelete();
            $table->string('step_id');
            $table->boolean('completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'roadmap_id', 'step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_progress');
        Schema::dropIfExists('roadmaps');
    }
};
