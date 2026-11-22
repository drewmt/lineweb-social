<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['user_id', 'id']);
        });

        Schema::create('saved_collection_posts', function (Blueprint $table): void {
            $table->foreignId('collection_id');
            $table->foreignId('user_id');
            $table->foreignId('post_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['collection_id', 'post_id']);
            $table->index(['user_id', 'collection_id']);
            $table->index(['user_id', 'post_id']);

            $table->foreign(['user_id', 'collection_id'], 'saved_collection_posts_collection_fk')
                ->references(['user_id', 'id'])->on('saved_collections')
                ->cascadeOnDelete();
            $table->foreign(['user_id', 'post_id'], 'saved_collection_posts_bookmark_fk')
                ->references(['user_id', 'post_id'])->on('post_saves')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_collection_posts');
        Schema::dropIfExists('saved_collections');
    }
};
