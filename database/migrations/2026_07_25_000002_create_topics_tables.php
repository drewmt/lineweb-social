<?php

use App\Community\Topics\TopicParser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->string('name', TopicParser::MAX_LENGTH)->unique();
        });

        Schema::create('post_topic', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();

            $table->primary(['post_id', 'topic_id']);
            $table->index(['topic_id', 'post_id']);
        });

        $parser = new TopicParser;

        DB::table('posts')
            ->select(['id', 'body'])
            ->orderBy('id')
            ->chunkById(250, function ($posts) use ($parser): void {
                foreach ($posts as $post) {
                    $names = $parser->names((string) $post->body);

                    if ($names === []) {
                        continue;
                    }

                    DB::table('topics')->insertOrIgnore(
                        array_map(
                            fn (string $name): array => ['name' => $name],
                            $names,
                        ),
                    );

                    $topicIds = DB::table('topics')
                        ->whereIn('name', $names)
                        ->pluck('id');

                    DB::table('post_topic')->insertOrIgnore(
                        $topicIds
                            ->map(fn (int $topicId): array => [
                                'post_id' => $post->id,
                                'topic_id' => $topicId,
                            ])
                            ->all(),
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_topic');
        Schema::dropIfExists('topics');
    }
};
