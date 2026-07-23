<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('published_at');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('edited_at');
        });
    }
};
