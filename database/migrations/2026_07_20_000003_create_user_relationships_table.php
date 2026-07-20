<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamps();

            $table->unique(['actor_id', 'target_id', 'type']);
            $table->index(['target_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_relationships');
    }
};
