<?php

namespace Tests\Feature;

use App\Community\ProvisionStarterCommunity;
use App\Enums\SpaceAuditAction;
use App\Enums\SpaceVisibility;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\SpacePostHighlight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterCommunityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_member_receives_an_audited_starter_community(): void
    {
        $member = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('platform:starter-community', [
            'email' => 'OWNER@example.com',
            '--confirm' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Starter community created: Community HQ');

        $space = Space::query()->sole();

        $this->assertTrue($space->owner->is($member));
        $this->assertSame(SpaceVisibility::Private, $space->visibility);
        $this->assertSame(3, Post::query()->whereBelongsTo($space)->count());
        $this->assertDatabaseHas('space_members', [
            'space_id' => $space->getKey(),
            'user_id' => $member->getKey(),
            'role' => 'owner',
        ]);

        $highlight = SpacePostHighlight::query()->sole();
        $this->assertSame($space->getKey(), $highlight->space_id);
        $this->assertSame($member->getKey(), $highlight->highlighted_by);

        $audit = SpaceAuditLog::query()
            ->where('action', SpaceAuditAction::StarterProvisioned->value)
            ->sole();

        $this->assertSame(ProvisionStarterCommunity::BLUEPRINT, $audit->context['blueprint']);
        $this->assertCount(3, $audit->context['post_ids']);
    }

    public function test_repeated_command_returns_the_existing_blueprint_without_duplicates(): void
    {
        $member = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('platform:starter-community', [
            'email' => $member->email,
            '--name' => 'Founders Circle',
            '--visibility' => 'public',
            '--confirm' => true,
        ])->assertSuccessful();

        $this->artisan('platform:starter-community', [
            'email' => $member->email,
            '--name' => 'A different name',
            '--visibility' => 'hidden',
            '--confirm' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Starter community already exists: Founders Circle');

        $this->assertSame(1, Space::query()->count());
        $this->assertSame(3, Post::query()->count());
        $this->assertSame(1, SpacePostHighlight::query()->count());
        $this->assertSame(
            1,
            SpaceAuditLog::query()
                ->where('action', SpaceAuditAction::StarterProvisioned->value)
                ->count(),
        );
        $this->assertSame(SpaceVisibility::Public, Space::query()->sole()->visibility);
    }

    public function test_command_requires_explicit_confirmation(): void
    {
        $member = User::factory()->create();

        $this->artisan('platform:starter-community', [
            'email' => $member->email,
        ])->assertFailed()
            ->expectsOutputToContain('Pass --confirm');

        $this->assertDatabaseEmpty('spaces');
        $this->assertDatabaseEmpty('posts');
    }

    public function test_unverified_member_cannot_receive_a_starter_community(): void
    {
        $member = User::factory()->unverified()->create();

        $this->artisan('platform:starter-community', [
            'email' => $member->email,
            '--confirm' => true,
        ])->assertFailed()
            ->expectsOutputToContain('must verify their email');

        $this->assertDatabaseEmpty('spaces');
    }

    public function test_suspended_member_cannot_receive_a_starter_community(): void
    {
        $member = User::factory()->create(['suspended_at' => now()]);

        $this->artisan('platform:starter-community', [
            'email' => $member->email,
            '--confirm' => true,
        ])->assertFailed()
            ->expectsOutputToContain('suspended member');

        $this->assertDatabaseEmpty('spaces');
    }

    public function test_command_rejects_unknown_visibility_without_writes(): void
    {
        $member = User::factory()->create();

        $this->artisan('platform:starter-community', [
            'email' => $member->email,
            '--visibility' => 'members-only',
            '--confirm' => true,
        ])->assertFailed()
            ->expectsOutputToContain('Visibility must be public, private, or hidden.');

        $this->assertDatabaseEmpty('spaces');
    }
}
