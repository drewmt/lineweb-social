<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string|null $headline
 * @property string $email
 * @property string|null $bio
 * @property string|null $location
 * @property string|null $website_url
 * @property ProfileVisibility $profile_visibility
 * @property bool $is_discoverable
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $shared_space_count
 * @property-read NotificationPreference|null $notificationPreference
 */
#[Fillable([
    'name',
    'handle',
    'headline',
    'email',
    'bio',
    'location',
    'website_url',
    'profile_visibility',
    'is_discoverable',
    'password',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'profile_visibility' => 'members',
        'is_discoverable' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->handle)) {
                $user->handle = static::availableHandle($user->name);
            }
        });

        static::deleting(function (User $user): void {
            PostMedia::query()
                ->whereHas('post', fn (Builder $posts) => $posts
                    ->where('user_id', $user->getKey())
                    ->orWhereHas('space', fn (Builder $spaces) => $spaces
                        ->where('owner_id', $user->getKey())))
                ->eachById(function (PostMedia $media): void {
                    $media->deleteStoredFile();
                });
            $user->notifications()->delete();
            $user->tokens()->delete();
        });
    }

    public static function availableHandle(string $name): string
    {
        $base = Str::slug($name) ?: 'member';
        $base = Str::limit($base, 30, '');

        do {
            $handle = $base.'-'.Str::lower(Str::random(6));
        } while (static::query()->where('handle', $handle)->exists());

        return $handle;
    }

    /**
     * Spaces the user belongs to.
     *
     * @return BelongsToMany<Space, $this>
     */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'space_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Posts authored by the user.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** @return HasMany<PostSave, $this> */
    public function postSaves(): HasMany
    {
        return $this->hasMany(PostSave::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return HasOne<NotificationPreference, $this> */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /** @return HasMany<PostReport, $this> */
    public function submittedPostReports(): HasMany
    {
        return $this->hasMany(PostReport::class, 'reporter_id');
    }

    /** @return HasMany<PostReport, $this> */
    public function reviewedPostReports(): HasMany
    {
        return $this->hasMany(PostReport::class, 'reviewed_by');
    }

    /** @return HasMany<CommentReport, $this> */
    public function submittedCommentReports(): HasMany
    {
        return $this->hasMany(CommentReport::class, 'reporter_id');
    }

    /** @return HasMany<CommentReport, $this> */
    public function reviewedCommentReports(): HasMany
    {
        return $this->hasMany(CommentReport::class, 'reviewed_by');
    }

    /** @return HasMany<SpaceInvitation, $this> */
    public function acceptedSpaceInvitations(): HasMany
    {
        return $this->hasMany(SpaceInvitation::class, 'accepted_by');
    }

    /** @return HasMany<UserRelationship, $this> */
    public function outgoingRelationships(): HasMany
    {
        return $this->hasMany(UserRelationship::class, 'actor_id');
    }

    /** @return HasMany<UserRelationship, $this> */
    public function incomingRelationships(): HasMany
    {
        return $this->hasMany(UserRelationship::class, 'target_id');
    }

    /**
     * Limit profiles to those the viewer may open directly.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $query
            ->where(function ($query) use ($viewer): void {
                $query
                    ->whereKey($viewer->getKey())
                    ->orWhere('profile_visibility', ProfileVisibility::Public)
                    ->orWhere(function ($query) use ($viewer): void {
                        $query
                            ->where('profile_visibility', ProfileVisibility::Members)
                            ->whereExists(function ($sharedSpaces) use ($viewer): void {
                                $sharedSpaces
                                    ->selectRaw('1')
                                    ->from('space_members as profile_spaces')
                                    ->join(
                                        'space_members as viewer_spaces',
                                        'viewer_spaces.space_id',
                                        '=',
                                        'profile_spaces.space_id',
                                    )
                                    ->whereColumn('profile_spaces.user_id', 'users.id')
                                    ->where('viewer_spaces.user_id', $viewer->getKey());
                            });
                    });
            })
            ->whereNotExists(function ($blocked) use ($viewer): void {
                $blocked
                    ->selectRaw('1')
                    ->from('user_relationships')
                    ->where('type', UserRelationshipType::Block)
                    ->where(function ($pair) use ($viewer): void {
                        $pair
                            ->where(function ($outgoing) use ($viewer): void {
                                $outgoing
                                    ->where('actor_id', $viewer->getKey())
                                    ->whereColumn('target_id', 'users.id');
                            })
                            ->orWhere(function ($incoming) use ($viewer): void {
                                $incoming
                                    ->whereColumn('actor_id', 'users.id')
                                    ->where('target_id', $viewer->getKey());
                            });
                    });
            });
    }

    /**
     * Limit profiles to people who opted into discovery and are visible.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDiscoverableBy(Builder $query, User $viewer): Builder
    {
        return $query
            ->where('is_discoverable', true)
            ->visibleTo($viewer);
    }

    public function sharesSpaceWith(User $viewer): bool
    {
        if ($this->is($viewer)) {
            return true;
        }

        return DB::table('space_members as profile_spaces')
            ->join('space_members as viewer_spaces', 'viewer_spaces.space_id', '=', 'profile_spaces.space_id')
            ->where('profile_spaces.user_id', $this->getKey())
            ->where('viewer_spaces.user_id', $viewer->getKey())
            ->exists();
    }

    public function hasMuted(User $target): bool
    {
        return $this->outgoingRelationships()
            ->whereBelongsTo($target, 'target')
            ->where('type', UserRelationshipType::Mute)
            ->exists();
    }

    public function hasBlocked(User $target): bool
    {
        return $this->outgoingRelationships()
            ->whereBelongsTo($target, 'target')
            ->where('type', UserRelationshipType::Block)
            ->exists();
    }

    public function isBlockedWith(User $other): bool
    {
        return UserRelationship::query()
            ->where('type', UserRelationshipType::Block)
            ->where(function ($query) use ($other): void {
                $query
                    ->where(function ($outgoing) use ($other): void {
                        $outgoing
                            ->where('actor_id', $this->getKey())
                            ->where('target_id', $other->getKey());
                    })
                    ->orWhere(function ($incoming) use ($other): void {
                        $incoming
                            ->where('actor_id', $other->getKey())
                            ->where('target_id', $this->getKey());
                    });
            })
            ->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'profile_visibility' => ProfileVisibility::class,
            'is_discoverable' => 'boolean',
        ];
    }
}
