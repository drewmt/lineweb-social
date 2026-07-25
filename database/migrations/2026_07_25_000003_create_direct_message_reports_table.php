<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_message_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('direct_message_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('reporter_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('reported_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reason', 40);
            $table->text('details')->nullable();
            $table->text('message_body_snapshot');
            $table->timestamp('message_sent_at')->nullable();
            $table->string('status', 24)->default('open');
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewer_note', 500)->nullable();
            $table->timestamps();

            $table->unique(['direct_message_id', 'reporter_id']);
            $table->index(['status', 'created_at']);
            $table->index(['reported_user_id', 'created_at']);
            $table->index(['reviewed_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_message_reports');
    }
};
