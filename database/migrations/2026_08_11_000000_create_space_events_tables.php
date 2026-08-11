<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone', 64);
            $table->string('venue', 160)->nullable();
            $table->string('online_url', 2048)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['space_id', 'starts_at', 'id']);
            $table->index(['space_id', 'cancelled_at', 'starts_at']);
        });

        Schema::create('space_event_rsvps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['space_event_id', 'user_id']);
            $table->index(['space_event_id', 'status']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_event_rsvps');
        Schema::dropIfExists('space_events');
    }
};
