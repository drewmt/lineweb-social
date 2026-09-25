<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $post_id
 * @property int $space_id
 * @property string $status
 * @property string $description
 * @property string|null $source_path
 * @property string|null $output_path
 * @property string|null $poster_path
 * @property int|null $duration_ms
 * @property int|null $width
 * @property int|null $height
 * @property int|null $input_bytes
 * @property int|null $output_bytes
 * @property int $reserved_bytes
 * @property string|null $checksum
 * @property string|null $failure_code
 * @property Carbon|null $expires_at
 * @property-read Post $post
 * @property-read Space $space
 */
class PostVideo extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'post_id',
        'space_id',
        'status',
        'description',
        'source_path',
        'output_path',
        'poster_path',
        'duration_ms',
        'width',
        'height',
        'input_bytes',
        'output_bytes',
        'reserved_bytes',
        'checksum',
        'failure_code',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
