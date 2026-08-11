<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpaceEvent> */
class SpaceEventFactory extends Factory
{
    protected $model = SpaceEvent::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(2, 30))->startOfHour();

        return [
            'space_id' => Space::factory(),
            'created_by' => User::factory(),
            'title' => rtrim((string) fake()->sentence(4), '.'),
            'description' => fake()->paragraph(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'timezone' => 'Europe/Athens',
            'venue' => fake()->streetAddress(),
            'online_url' => null,
            'capacity' => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'cancelled_at' => now(),
        ]);
    }
}
