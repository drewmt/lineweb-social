<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->text('description');
            $table->string('source_path')->nullable();
            $table->string('output_path')->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('input_bytes')->nullable();
            $table->unsignedBigInteger('output_bytes')->nullable();
            $table->unsignedBigInteger('reserved_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_videos');
    }
};
