<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_feeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
            $table->index(['user_id', 'id']);
        });

        foreach (['people' => ['person_id', 'users'], 'spaces' => ['space_id', 'spaces'], 'topics' => ['topic_id', 'topics']] as $type => [$column, $target]) {
            Schema::create('personal_feed_'.$type, function (Blueprint $table) use ($column, $target): void {
                $table->foreignId('personal_feed_id')->constrained()->cascadeOnDelete();
                $table->foreignId($column)->constrained($target)->cascadeOnDelete();
                $table->primary(['personal_feed_id', $column]);
                $table->index($column);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_feed_topics');
        Schema::dropIfExists('personal_feed_spaces');
        Schema::dropIfExists('personal_feed_people');
        Schema::dropIfExists('personal_feeds');
    }
};
