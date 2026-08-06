<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Comment> */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'published_at' => now(),
        ];
    }

    public function replyingTo(Comment $parent): static
    {
        return $this->state(fn (): array => [
            'post_id' => $parent->post_id,
            'parent_id' => $parent->getKey(),
        ]);
    }
}
