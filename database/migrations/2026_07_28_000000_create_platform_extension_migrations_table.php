<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_extension_migrations', function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id', 80);
            $table->string('migration', 180);
            $table->string('extension_version', 40);
            $table->char('checksum', 64);
            $table->unsignedInteger('batch');
            $table->timestamp('applied_at')->useCurrent();

            $table->unique(['extension_id', 'migration']);
            $table->index(['extension_id', 'batch']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_extension_migrations');
    }
};
