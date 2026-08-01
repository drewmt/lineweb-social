<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->foreignId('shared_post_id')
                ->nullable()
                ->after('body')
                ->constrained('posts')
                ->nullOnDelete();
            $table->unique(['user_id', 'shared_post_id'], 'posts_author_shared_post_unique');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropUnique('posts_author_shared_post_unique');
            $table->dropForeign(['shared_post_id']);
            $table->dropColumn('shared_post_id');
        });
    }
};
