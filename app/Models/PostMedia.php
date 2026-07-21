<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $post_id
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $width
 * @property int $height
 * @property int $size_bytes
 * @property string $checksum
 * @property string $alt_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Post $post
 */
class PostMedia extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (PostMedia $media): void {
            $media->deleteStoredFile();
        });
    }

    protected $table = 'post_media';

    protected $fillable = [
        'disk',
        'path',
        'mime_type',
        'width',
        'height',
        'size_bytes',
        'checksum',
        'alt_text',
    ];

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function deleteStoredFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
