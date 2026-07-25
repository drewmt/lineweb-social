<?php

use App\Enums\PlatformAppealStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('suspension_reference')
                ->nullable()
                ->unique()
                ->after('suspended_at');
        });

        Schema::create('platform_appeals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('suspension_reference')->unique();
            $table->timestamp('suspension_started_at');
            $table->string('status', 24)->default(PlatformAppealStatus::Open->value);
            $table->text('statement');
            $table->string('decision_message', 500)->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_appeals');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['suspension_reference']);
            $table->dropColumn('suspension_reference');
        });
    }
};
