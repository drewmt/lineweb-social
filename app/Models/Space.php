<?php

namespace App\Models;

use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property SpaceVisibility $visibility
 * @property-read bool $is_member
 * @property-read string|null $current_role
 * @property-read int $members_count
 */
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Space $space): void {
            $space->members()->syncWithoutDetaching([
                $space->owner_id => ['role' => SpaceRole::Owner->value],
            ]);
        });
    }

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'visibility',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => SpaceVisibility::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'space_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** @return HasMany<SpaceInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(SpaceInvitation::class);
    }

    /** @return HasMany<SpaceAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(SpaceAuditLog::class);
    }

    /** @return HasMany<PostReport, $this> */
    public function postReports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    /** @return HasMany<CommentReport, $this> */
    public function commentReports(): HasMany
    {
        return $this->hasMany(CommentReport::class);
    }

    /**
     * Limit a query to spaces a user is allowed to discover.
     *
     * @param  Builder<Space>  $query
     * @return Builder<Space>
     */
    public function scopeDiscoverableBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where('visibility', SpaceVisibility::Public)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->getKey()));
        });
    }

    public function hasMember(User $user): bool
    {
        if ($this->relationLoaded('members')) {
            return $this->members->contains($user);
        }

        return $this->members()->whereKey($user->getKey())->exists();
    }

    public function addMember(User $user, SpaceRole $role = SpaceRole::Member): void
    {
        $this->members()->syncWithoutDetaching([
            $user->getKey() => ['role' => $role->value],
        ]);
    }

    public function roleFor(User $user): ?SpaceRole
    {
        $role = $this->members()
            ->whereKey($user->getKey())
            ->value('space_members.role');

        return is_string($role) ? SpaceRole::tryFrom($role) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
