<?php

namespace Tests\Feature;

use App\Enums\ReportAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\SpaceAuditAction;
use App\Enums\SpaceRole;
use App\Enums\UserRelationshipType;
use App\Events\PostReported;
use App\Events\PostReportModerated;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_post_can_be_reported_once_and_dispatches_an_extension_event(): void
    {
        Event::fake([PostReported::class]);

        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->create();
        $post = Post::factory()->for($space)->for($author, 'author')->create();

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $post), [
                'reason' => ReportReason::Spam->value,
                'details' => '  Repeated promotional links.  ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('post_reports', [
            'space_id' => $space->getKey(),
            'post_id' => $post->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reason' => ReportReason::Spam->value,
            'details' => 'Repeated promotional links.',
            'status' => ReportStatus::Open->value,
        ]);
        Event::assertDispatched(PostReported::class);

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $post), [
                'reason' => ReportReason::Privacy->value,
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('post_reports', 1);
    }

    public function test_other_reason_requires_context_and_input_is_allowlisted(): void
    {
        $reporter = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $post), [
                'reason' => ReportReason::Other->value,
                'details' => '',
            ])
            ->assertSessionHasErrors('details');

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $post), [
                'reason' => 'custom_reason',
                'details' => 'Not an allowlisted reason.',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertDatabaseCount('post_reports', 0);
    }

    public function test_self_reports_invisible_posts_and_block_bypasses_are_forbidden(): void
    {
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $private = Space::factory()->private()->create();
        $privatePost = Post::factory()->for($private)->for($author, 'author')->create();
        $ownPost = Post::factory()->for($private)->for($reporter, 'author')->create();

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $privatePost), [
                'reason' => ReportReason::Spam->value,
            ])
            ->assertForbidden();

        $private->addMember($reporter);

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $ownPost), [
                'reason' => ReportReason::Spam->value,
            ])
            ->assertForbidden();

        $reporter->outgoingRelationships()->create([
            'target_id' => $author->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $this->actingAs($reporter)
            ->post(route('posts.reports.store', $privatePost), [
                'reason' => ReportReason::Harassment->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('post_reports', 0);
    }

    public function test_only_space_moderators_can_open_the_private_review_queue(): void
    {
        $owner = User::factory()->create();
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $space->addMember($member);
        $post = Post::factory()->for($space)->for($author, 'author')->create(['body' => 'Reported post body']);
        $report = PostReport::factory()->for($space)->for($post)->for($reporter, 'reporter')->create([
            'reason' => ReportReason::Privacy,
            'details' => 'Contains personal information.',
        ]);

        $this->actingAs($member)
            ->get(route('spaces.moderation.index', $space))
            ->assertForbidden();

        $this->actingAs($moderator)
            ->get(route('spaces.moderation.index', $space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('spaces/moderation')
                ->where('counts.active', 1)
                ->has('reports', 1)
                ->where('reports.0.id', $report->getKey())
                ->where('reports.0.reporter.name', $reporter->name)
                ->where('reports.0.contentType', 'post')
                ->where('reports.0.content.body', 'Reported post body'));
    }

    public function test_nested_report_binding_does_not_cross_space_boundaries(): void
    {
        $owner = User::factory()->create();
        $first = Space::factory()->for($owner, 'owner')->create();
        $second = Space::factory()->for($owner, 'owner')->create();
        $post = Post::factory()->for($first)->create();
        $report = PostReport::factory()->for($first)->for($post)->create();

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$second, $report]), [
                'action' => ReportAction::Review->value,
            ])
            ->assertNotFound();
    }

    public function test_moderator_can_hide_and_restore_a_reported_post_with_audited_decisions(): void
    {
        Event::fake([PostReportModerated::class]);

        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($reporter);
        $space->addMember($author);
        $post = Post::factory()->for($space)->for($author, 'author')->create(['body' => 'Safety-sensitive post']);
        $report = PostReport::factory()->for($space)->for($post)->for($reporter, 'reporter')->create();

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$space, $report]), [
                'action' => ReportAction::Hide->value,
                'note' => 'Violates the published community rule.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('posts', [
            'id' => $post->getKey(),
            'hidden_by' => $owner->getKey(),
            'moderation_note' => 'Violates the published community rule.',
        ]);
        $this->assertNotNull($post->fresh()->hidden_at);
        $this->assertDatabaseHas('post_reports', [
            'id' => $report->getKey(),
            'status' => ReportStatus::Resolved->value,
            'reviewed_by' => $owner->getKey(),
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'action' => SpaceAuditAction::PostReportResolved->value,
            'reason' => 'Violates the published community rule.',
        ]);
        Event::assertDispatched(PostReportModerated::class, fn (PostReportModerated $event): bool => $event->action === ReportAction::Hide);

        $this->actingAs($reporter)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page->has('posts', 0));
        $this->actingAs($reporter)
            ->get(route('people.show', $author))
            ->assertInertia(fn (Assert $page) => $page->has('posts', 0));

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$space, $report]), [
                'action' => ReportAction::Reopen->value,
                'note' => 'Restored after a second review.',
            ])
            ->assertRedirect();

        $this->assertNull($post->fresh()->hidden_at);
        $this->assertDatabaseHas('post_reports', [
            'id' => $report->getKey(),
            'status' => ReportStatus::Reviewing->value,
            'moderator_note' => 'Restored after a second review.',
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'action' => SpaceAuditAction::PostReportReopened->value,
        ]);
    }

    public function test_moderation_decisions_require_notes_and_follow_valid_transitions(): void
    {
        $owner = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $post = Post::factory()->for($space)->create();
        $report = PostReport::factory()->for($space)->for($post)->create();

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$space, $report]), [
                'action' => ReportAction::Dismiss->value,
                'note' => '',
            ])
            ->assertSessionHasErrors('note');

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$space, $report]), [
                'action' => ReportAction::Review->value,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$space, $report]), [
                'action' => ReportAction::Review->value,
            ])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseHas('post_reports', [
            'id' => $report->getKey(),
            'status' => ReportStatus::Reviewing->value,
        ]);
    }

    public function test_reopening_one_report_does_not_override_another_removal_decision(): void
    {
        $owner = User::factory()->create();
        $firstReporter = User::factory()->create();
        $secondReporter = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $post = Post::factory()->for($space)->create();
        $first = PostReport::factory()->for($space)->for($post)->for($firstReporter, 'reporter')->create();
        $second = PostReport::factory()->for($space)->for($post)->for($secondReporter, 'reporter')->create();

        foreach ([$first, $second] as $report) {
            $this->actingAs($owner)
                ->patch(route('spaces.moderation.reports.update', [$space, $report]), [
                    'action' => ReportAction::Hide->value,
                    'note' => 'Independent removal decision.',
                ])
                ->assertRedirect();
        }

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$space, $first]), [
                'action' => ReportAction::Reopen->value,
                'note' => 'First report needs another look.',
            ])
            ->assertRedirect();

        $this->assertNotNull($post->fresh()->hidden_at);

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.reports.update', [$space, $second]), [
                'action' => ReportAction::Reopen->value,
                'note' => 'Second report also needs another look.',
            ])
            ->assertRedirect();

        $this->assertNull($post->fresh()->hidden_at);
    }
}
