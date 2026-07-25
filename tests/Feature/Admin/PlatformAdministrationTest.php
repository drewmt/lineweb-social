<?php

namespace Tests\Feature\Admin;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_members_cannot_access_platform_administration(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('admin.members.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('admin.audit.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->put(route('admin.members.suspension.store', $member), [
                'reason' => 'Attempted privilege escalation.',
            ])
            ->assertForbidden();
    }

    public function test_administrator_can_view_real_platform_metrics_and_search_members(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
            'name' => 'Platform Owner',
        ]);
        $target = User::factory()->create([
            'name' => 'Target Member',
            'email' => 'target@example.com',
        ]);
        User::factory()->create(['name' => 'Different Member']);
        Space::factory()->for($target, 'owner')->create();

        $this->actingAs($administrator)
            ->get(route('admin.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/index')
                ->where('metrics.membersTotal', 3)
                ->where('metrics.membersSuspended', 0)
                ->where('metrics.administratorsTotal', 1)
                ->where('metrics.spacesTotal', 1)
                ->where('metrics.communityReportsActive', 0)
                ->where('metrics.messageReportsActive', 0)
                ->has('auditLogs', 0));

        $this->actingAs($administrator)
            ->get(route('admin.members.index', ['q' => 'target@example.com']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/members')
                ->where('query', 'target@example.com')
                ->where('filter', 'all')
                ->where('counts.all', 3)
                ->where('counts.administrators', 1)
                ->has('members.data', 1)
                ->where('members.data.0.name', 'Target Member')
                ->where('members.data.0.email', 'target@example.com')
                ->where('members.data.0.platformRole', 'member')
                ->where('members.data.0.canSuspend', true));
    }

    public function test_administrator_can_suspend_a_member_with_an_audited_reason(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);
        $member = User::factory()->create([
            'remember_token' => 'known-remember-token',
        ]);
        $token = $member->createToken('Mobile', ['profile:read'])->accessToken;
        DB::table('sessions')->insert([
            'id' => 'member-session',
            'user_id' => $member->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($administrator)
            ->put(route('admin.members.suspension.store', [
                'member' => $member,
                'q' => 'known member',
                'status' => 'active',
            ]), [
                'reason' => 'Repeated targeted harassment after a warning.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.members.index', [
                'q' => 'known member',
                'status' => 'active',
            ]));

        $member->refresh();

        $this->assertNotNull($member->suspended_at);
        $this->assertSame(
            'Repeated targeted harassment after a warning.',
            $member->suspension_reason,
        );
        $this->assertSame($administrator->getKey(), $member->suspended_by);
        $this->assertNotSame('known-remember-token', $member->remember_token);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->getKey(),
        ]);
        $this->assertDatabaseMissing('sessions', ['id' => 'member-session']);
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $administrator->getKey(),
            'subject_user_id' => $member->getKey(),
            'action' => 'member.suspended',
            'reason' => 'Repeated targeted harassment after a warning.',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.audit.index', [
                'category' => 'accounts',
                'q' => 'targeted harassment',
            ]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit')
                ->where('category', 'accounts')
                ->where('query', 'targeted harassment')
                ->where('counts.accounts', 1)
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'member.suspended')
                ->where('logs.data.0.actorName', $administrator->name)
                ->where('logs.data.0.subjectHandle', $member->handle)
                ->where(
                    'logs.data.0.reason',
                    'Repeated targeted harassment after a warning.',
                ));
    }

    public function test_suspension_requires_a_meaningful_reason(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);
        $member = User::factory()->create();

        $this->actingAs($administrator)
            ->put(route('admin.members.suspension.store', $member), [
                'reason' => 'short',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertNull($member->refresh()->suspended_at);
    }

    public function test_administrator_cannot_suspend_self_or_another_administrator(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);
        $otherAdministrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);

        $this->actingAs($administrator)
            ->put(route('admin.members.suspension.store', $administrator), [
                'reason' => 'This must never lock out the current operator.',
            ])
            ->assertSessionHasErrors('member');

        $this->actingAs($administrator)
            ->put(route('admin.members.suspension.store', $otherAdministrator), [
                'reason' => 'Peer administrators are protected from web suspension.',
            ])
            ->assertSessionHasErrors('member');

        $this->assertNull($administrator->refresh()->suspended_at);
        $this->assertNull($otherAdministrator->refresh()->suspended_at);
    }

    public function test_suspended_member_is_restricted_but_can_reach_account_data_rights(): void
    {
        $member = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'Abuse review in progress.',
        ]);

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertRedirect(route('account.status'));

        $this->actingAs($member)
            ->get(route('account.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/status')
                ->where('account.handle', $member->handle)
                ->has('deletionBlockers'));

        $this->actingAs($member)
            ->get(route('personal-data.export'))
            ->assertRedirect(route('password.confirm'));

        $token = $member->createToken('Unexpected token', ['profile:read']);

        $this->withToken($token->plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_suspended_unverified_member_sees_restriction_before_verification(): void
    {
        $member = User::factory()->unverified()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'Access is under review.',
        ]);

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertRedirect(route('account.status'));

        $this->actingAs($member)
            ->get(route('account.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/status')
                ->where('account.emailVerified', false));
    }

    public function test_administrator_can_reinstate_a_suspended_member(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);
        $member = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'Temporary investigation.',
            'suspended_by' => $administrator->getKey(),
        ]);

        $this->actingAs($administrator)
            ->delete(route('admin.members.suspension.destroy', $member), [
                'reason' => 'Review completed and access can be restored.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.members.index'));

        $member->refresh();

        $this->assertNull($member->suspended_at);
        $this->assertNull($member->suspension_reason);
        $this->assertNull($member->suspended_by);
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $administrator->getKey(),
            'subject_user_id' => $member->getKey(),
            'action' => 'member.reinstated',
            'reason' => 'Review completed and access can be restored.',
        ]);

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertOk();
    }

    public function test_member_directory_filters_accounts_without_weakening_protected_actions(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);
        $suspended = User::factory()->create([
            'name' => 'Restricted Member',
            'suspended_at' => now(),
            'suspension_reason' => 'A documented access restriction.',
            'suspended_by' => $administrator->getKey(),
        ]);
        User::factory()->unverified()->create();
        User::factory()->create();

        $this->actingAs($administrator)
            ->get(route('admin.members.index', ['status' => 'suspended']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/members')
                ->where('filter', 'suspended')
                ->where('counts.all', 4)
                ->where('counts.suspended', 1)
                ->where('counts.unverified', 1)
                ->has('members.data', 1)
                ->where('members.data.0.handle', $suspended->handle)
                ->where(
                    'members.data.0.suspensionReason',
                    'A documented access restriction.',
                ));

        $this->actingAs($administrator)
            ->get(route('admin.members.index', ['status' => 'unknown']))
            ->assertSessionHasErrors('status');
    }

    public function test_console_command_bootstraps_admins_and_protects_the_last_one(): void
    {
        $first = User::factory()->create(['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);

        $this->artisan('platform:administrator', [
            'email' => $first->email,
        ])->assertSuccessful();
        $this->assertSame('administrator', $first->refresh()->platform_role->value);

        $this->artisan('platform:administrator', [
            'email' => $first->email,
            '--revoke' => true,
        ])->assertFailed();
        $this->assertSame('administrator', $first->refresh()->platform_role->value);

        $this->artisan('platform:administrator', [
            'email' => $second->email,
        ])->assertSuccessful();
        $this->artisan('platform:administrator', [
            'email' => $first->email,
            '--revoke' => true,
        ])->assertSuccessful();

        $this->assertSame('member', $first->refresh()->platform_role->value);
        $this->assertSame('administrator', $second->refresh()->platform_role->value);
    }

    public function test_last_administrator_cannot_delete_their_account(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);

        $this->actingAs($administrator)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('account');

        $this->assertNotNull($administrator->fresh());
    }
}
