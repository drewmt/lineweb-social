<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('question', 180);
            $table->unsignedTinyInteger('closes_after_days')->nullable();
            $table->timestamp('closes_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('post_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_poll_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('label', 100);
            $table->timestamps();

            $table->unique(['post_poll_id', 'position']);
        });

        Schema::create('post_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['post_poll_id', 'user_id']);
            $table->index(['post_poll_option_id', 'post_poll_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_poll_votes');
        Schema::dropIfExists('post_poll_options');
        Schema::dropIfExists('post_polls');
    }
};
