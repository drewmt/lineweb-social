<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommentReport> */
class CommentReportFactory extends Factory
{
    protected $model = CommentReport::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'comment_id' => Comment::factory(),
            'space_id' => fn (array $attributes): int => Comment::query()
                ->with('post:id,space_id')
                ->whereKey($attributes['comment_id'])
                ->firstOrFail()
                ->post
                ->space_id,
            'reporter_id' => User::factory(),
            'reason' => fake()->randomElement(ReportReason::cases()),
            'details' => fake()->optional()->sentence(),
            'status' => ReportStatus::Open,
        ];
    }
}
