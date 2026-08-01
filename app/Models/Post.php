<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $space_id
 * @property int $user_id
 * @property string $body
 * @property int|null $shared_post_id
 * @property Carbon|null $published_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $hidden_at
 * @property int|null $hidden_by
 * @property string|null $moderation_note
 * @property-read Space $space
 * @property-read User $author
 * @property-read Post|null $sharedPost
 * @property-read PostMedia|null $media
 * @property-read Collection<int, PostMedia> $mediaItems
 * @property-read SpacePostHighlight|null $highlight
 * @property-read int $is_saved
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Post $post): void {
            $post->loadMissing('mediaItems');

            foreach ($post->mediaItems as $media) {
                $media->deleteStoredFile();
            }
        });
    }

    protected $fillable = [
        'space_id',
        'user_id',
        'body',
        'shared_post_id',
        'published_at',
        'edited_at',
        'hidden_at',
        'hidden_by',
        'moderation_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'edited_at' => 'immutable_datetime',
            'hidden_at' => 'immutable_datetime',
        ];
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

    /** @return BelongsTo<Post, $this> */
    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'shared_post_id');
    }

    /** @return BelongsTo<User, $this> */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    /** @return HasMany<PostReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasMany<PostSave, $this> */
    public function saves(): HasMany
    {
        return $this->hasMany(PostSave::class);
    }

    /** @return HasMany<PostReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    /** @return BelongsToMany<Topic, $this> */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class)->orderBy('topics.name');
    }

    /** @return HasOne<PostMedia, $this> */
    public function media(): HasOne
    {
        return $this->hasOne(PostMedia::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return HasMany<PostMedia, $this> */
    public function mediaItems(): HasMany
    {
        return $this->hasMany(PostMedia::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return HasOne<SpacePostHighlight, $this> */
    public function highlight(): HasOne
    {
        return $this->hasOne(SpacePostHighlight::class);
    }
}
