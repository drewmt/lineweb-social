<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PostReport> */
class PostReportFactory extends Factory
{
    protected $model = PostReport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'space_id' => fn (array $attributes): int => Post::query()
                ->whereKey($attributes['post_id'])
                ->firstOrFail()
                ->space_id,
            'reporter_id' => User::factory(),
            'reason' => fake()->randomElement(ReportReason::cases()),
            'details' => fake()->optional()->sentence(),
            'status' => ReportStatus::Open,
        ];
    }
}
