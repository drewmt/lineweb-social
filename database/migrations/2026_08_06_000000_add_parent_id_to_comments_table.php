<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('comments')
                ->nullOnDelete();

            $table->index(['parent_id', 'published_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex(['parent_id', 'published_at', 'id']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
