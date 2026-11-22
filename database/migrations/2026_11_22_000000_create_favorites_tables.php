<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_favorites', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'profile_id']);
        });

        Schema::create('space_favorites', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'space_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_favorites');
        Schema::dropIfExists('profile_favorites');
    }
};
