<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformAppealStatus;
use App\Models\PlatformAppeal;
use App\Models\User;
use App\Platform\PlatformAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAppealTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_can_view_good_account_status(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('account.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/status')
                ->where('account.handle', $member->handle)
                ->where('account.restricted', false)
                ->where('canAppeal', false)
                ->where('appeal', null)
                ->has('deletionBlockers', 0));
    }

    public function test_suspended_member_can_submit_one_appeal_for_the_current_restriction(): void
    {
        $member = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reason' => 'Private operator context that must not be shown.',
        ]);

        $this->actingAs($member)
            ->get(route('account.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('account/status')
                ->where('account.restricted', true)
                ->where('canAppeal', true)
                ->missing('account.suspensionReason'));

        $this->actingAs($member)
            ->post(route('account.appeals.store'), [
                'statement' => 'I believe this restriction should be reviewed because the context was misunderstood.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.status'));

        $appeal = PlatformAppeal::query()->sole();
        $this->assertSame($member->getKey(), $appeal->user_id);
        $this->assertSame(PlatformAppealStatus::Open, $appeal->status);
        $this->assertNotNull($member->refresh()->suspension_reference);
        $this->assertSame(
            $member->suspension_reference,
            $appeal->suspension_reference,
        );
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $member->getKey(),
            'subject_user_id' => $member->getKey(),
            'action' => 'appeal.submitted',
            'reason' => null,
        ]);
        $this->assertDatabaseMissing('platform_audit_logs', [
            'reason' => $appeal->statement,
        ]);

        $this->actingAs($member)
            ->post(route('account.appeals.store'), [
                'statement' => 'This second appeal must not create another review record.',
            ])
            ->assertSessionHasErrors('statement');

        $this->assertDatabaseCount('platform_appeals', 1);
    }

    public function test_active_member_cannot_submit_an_appeal_or_open_admin_queue(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->post(route('account.appeals.store'), [
                'statement' => 'There is no active restriction to review on this account.',
            ])
            ->assertSessionHasErrors('statement');

        $this->actingAs($member)
            ->get(route('admin.appeals.index'))
            ->assertForbidden();

        $this->assertDatabaseCount('platform_appeals', 0);
    }

    public function test_administrator_can_review_and_deny_an_appeal_without_restoring_access(): void
    {
        [$administrator, $member, $appeal] = $this->appealFixture();

        $this->actingAs($administrator)
            ->patch(route('admin.appeals.update', $appeal), [
                'action' => 'review',
                'decision_message' => 'Your appeal is being reviewed by a platform administrator.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.appeals.index'));

        $this->assertSame(
            PlatformAppealStatus::Reviewing,
            $appeal->refresh()->status,
        );

        $this->actingAs($administrator)
            ->patch(route('admin.appeals.update', $appeal), [
                'action' => 'deny',
                'decision_message' => 'We reviewed the context and the account restriction will remain in place.',
            ])
            ->assertSessionHasNoErrors();

        $appeal->refresh();
        $this->assertSame(PlatformAppealStatus::Denied, $appeal->status);
        $this->assertSame($administrator->getKey(), $appeal->reviewed_by);
        $this->assertTrue($member->refresh()->isSuspended());

        $this->actingAs($member)
            ->get(route('account.status'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('appeal.status', 'denied')
                ->where(
                    'appeal.decisionMessage',
                    'We reviewed the context and the account restriction will remain in place.',
                )
                ->where('canAppeal', false)
                ->missing('appeal.reviewerName')
                ->missing('account.suspensionReason'));
    }

    public function test_approving_an_appeal_restores_access_and_records_both_decisions(): void
    {
        [$administrator, $member, $appeal] = $this->appealFixture();

        $this->actingAs($administrator)
            ->patch(route('admin.appeals.update', $appeal), [
                'action' => 'approve',
                'decision_message' => 'Your appeal was approved and community access has been restored.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            PlatformAppealStatus::Approved,
            $appeal->refresh()->status,
        );
        $member->refresh();
        $this->assertFalse($member->isSuspended());
        $this->assertNull($member->suspension_reference);
        $this->assertNull($member->suspension_reason);
        $this->assertNull($member->suspended_by);
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $administrator->getKey(),
            'subject_user_id' => $member->getKey(),
            'action' => 'appeal.approved',
        ]);
        $this->assertDatabaseHas('platform_audit_logs', [
            'actor_id' => $administrator->getKey(),
            'subject_user_id' => $member->getKey(),
            'action' => 'member.reinstated',
        ]);

        $this->actingAs($member)
            ->get(route('feed'))
            ->assertOk();

        $this->actingAs($administrator)
            ->patch(route('admin.appeals.update', $appeal), [
                'action' => 'deny',
                'decision_message' => 'A final appeal decision cannot be changed through this queue.',
            ])
            ->assertSessionHasErrors('action');
    }

    public function test_member_directory_reinstatement_resolves_an_active_appeal_safely(): void
    {
        [$administrator, $member, $appeal] = $this->appealFixture();

        app(PlatformAdministration::class)->reinstate(
            $member,
            $administrator,
            'Internal investigation completed with no remaining access concern.',
        );

        $appeal->refresh();
        $this->assertSame(PlatformAppealStatus::Approved, $appeal->status);
        $this->assertSame(
            'Your account access was restored after administrator review.',
            $appeal->decision_message,
        );
        $this->assertFalse($member->refresh()->isSuspended());
        $this->assertNotSame(
            'Internal investigation completed with no remaining access concern.',
            $appeal->decision_message,
        );
    }

    public function test_a_new_restriction_cycle_allows_a_new_appeal(): void
    {
        [$administrator, $member, $firstAppeal] = $this->appealFixture();

        $this->actingAs($administrator)
            ->patch(route('admin.appeals.update', $firstAppeal), [
                'action' => 'approve',
                'decision_message' => 'The first review restored access to the community.',
            ])
            ->assertSessionHasNoErrors();

        app(PlatformAdministration::class)->suspend(
            $member->refresh(),
            $administrator,
            'A separate incident requires a new access review.',
        );

        $this->actingAs($member->refresh())
            ->post(route('account.appeals.store'), [
                'statement' => 'This appeal concerns the new and separate account restriction.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('platform_appeals', 2);
        $this->assertNotSame(
            $firstAppeal->suspension_reference,
            PlatformAppeal::query()->latest('id')->firstOrFail()->suspension_reference,
        );
    }

    /** @return array{User, User, PlatformAppeal} */
    private function appealFixture(): array
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);
        $reference = (string) Str::uuid();
        $member = User::factory()->create([
            'suspended_at' => now(),
            'suspension_reference' => $reference,
            'suspension_reason' => 'Repeated safety violations documented internally.',
            'suspended_by' => $administrator->getKey(),
        ]);
        $appeal = PlatformAppeal::query()->create([
            'user_id' => $member->getKey(),
            'suspension_reference' => $reference,
            'suspension_started_at' => $member->suspended_at,
            'status' => PlatformAppealStatus::Open,
            'statement' => 'Please review the restriction because I believe important context was missed.',
        ]);

        return [$administrator, $member, $appeal];
    }
}
