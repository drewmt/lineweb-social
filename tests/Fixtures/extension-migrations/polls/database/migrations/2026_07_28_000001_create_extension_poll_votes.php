<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_poll_votes');
    }
};
