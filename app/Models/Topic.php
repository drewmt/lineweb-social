<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property-read int $visible_posts_count
 */
class Topic extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
