<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->string('email_digest_frequency', 16)
                ->default('off')
                ->after('space_moderation');
            $table->timestamp('email_digest_cursor_at')
                ->nullable()
                ->after('email_digest_frequency');
            $table->uuid('email_digest_cursor_notification_id')
                ->nullable()
                ->after('email_digest_cursor_at');
            $table->index(
                'email_digest_frequency',
                'notification_preferences_digest_frequency_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropIndex('notification_preferences_digest_frequency_index');
            $table->dropColumn([
                'email_digest_frequency',
                'email_digest_cursor_at',
                'email_digest_cursor_notification_id',
            ]);
        });
    }
};
