<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $space_id
 * @property int $user_id
 * @property string|null $body
 * @property string $background
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $mime_type
 * @property int|null $width
 * @property int|null $height
 * @property int|null $size_bytes
 * @property string|null $checksum
 * @property string|null $alt_text
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property-read Space $space
 * @property-read User $author
 */
class Story extends Model
{
    /** @var list<string> */
    public const BACKGROUNDS = ['ink', 'ocean', 'violet', 'sunset', 'mint'];

    public const ACTIVE_LIMIT_PER_SPACE = 5;

    public const LIFETIME_HOURS = 24;

    protected static function booted(): void
    {
        static::deleting(function (Story $story): void {
            $story->deleteStoredFile();
        });
    }

    protected $fillable = [
        'space_id',
        'user_id',
        'body',
        'background',
        'disk',
        'path',
        'mime_type',
        'width',
        'height',
        'size_bytes',
        'checksum',
        'alt_text',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @param Builder<Story> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    public function hasImage(): bool
    {
        return filled($this->disk) && filled($this->path);
    }

    public function deleteStoredFile(): void
    {
        if ($this->hasImage()) {
            Storage::disk((string) $this->disk)->delete((string) $this->path);
        }
    }
}
