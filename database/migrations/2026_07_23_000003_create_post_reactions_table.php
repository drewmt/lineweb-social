<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->index(['post_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reactions');
    }
};
