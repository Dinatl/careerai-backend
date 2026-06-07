<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->string('icon')->default('sparkles');
            $table->string('action_key')->unique();
            $table->unsignedSmallInteger('points_required')->default(0);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('badge_id')->constrained()->onDelete('cascade');
            $table->timestamp('earned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('user_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->unsignedInteger('points')->default(0);
            $table->string('level')->default('Debutant');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_points');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};
