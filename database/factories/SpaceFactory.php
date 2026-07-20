<?php

namespace Database\Factories;

use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Space> */
class SpaceFactory extends Factory
{
    protected $model = Space::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = rtrim((string) fake()->unique()->sentence(2), '.');

        return [
            'owner_id' => User::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
            'description' => fake()->sentence(),
            'visibility' => SpaceVisibility::Public,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (): array => ['visibility' => SpaceVisibility::Private]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['visibility' => SpaceVisibility::Hidden]);
    }
}
