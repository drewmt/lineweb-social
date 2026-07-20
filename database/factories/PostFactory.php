<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'published_at' => now(),
        ];
    }
}
