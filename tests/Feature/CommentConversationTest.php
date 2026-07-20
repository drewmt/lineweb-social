<?php

namespace Tests\Feature;

use App\Enums\ReportAction;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\SpaceAuditAction;
use App\Events\CommentPublished;
use App\Events\CommentReported;
use App\Events\CommentReportModerated;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommentConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_space_member_can_publish_a_trimmed_comment_and_dispatch_an_event(): void
    {
        Event::fake([CommentPublished::class]);

        $member = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($member);
        $post = Post::factory()->for($space)->create();

        $this->actingAs($member)
            ->post(route('posts.comments.store', $post), ['body' => '  A useful reply.  '])
            ->assertRedirect();

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->getKey(),
            'user_id' => $member->getKey(),
            'body' => 'A useful reply.',
        ]);
        Event::assertDispatched(CommentPublished::class);
    }

    public function test_commenting_requires_membership_visibility_and_valid_content(): void
    {
        $visitor = User::factory()->create();
        $member = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($member);
        $post = Post::factory()->for($space)->create();
        $hiddenPost = Post::factory()->for($space)->create(['hidden_at' => now()]);

        $this->actingAs($visitor)
            ->post(route('posts.comments.store', $post), ['body' => 'Cannot join the thread.'])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('posts.comments.store', $hiddenPost), ['body' => 'Hidden conversation.'])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('posts.comments.store', $post), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($member)
            ->post(route('posts.comments.store', $post), ['body' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_feed_returns_visible_comments_in_conversation_order_and_respects_mute(): void
    {
        $viewer = User::factory()->create();
        $firstAuthor = User::factory()->create(['name' => 'Visible member']);
        $mutedAuthor = User::factory()->create(['name' => 'Muted member']);
        $space = Space::factory()->create();
        $space->addMember($viewer);
        $post = Post::factory()->for($space)->create();

        Comment::factory()->for($post)->for($firstAuthor, 'author')->create([
            'body' => 'First visible reply',
            'published_at' => now()->subMinute(),
        ]);
        Comment::factory()->for($post)->for($firstAuthor, 'author')->create([
            'body' => 'Second visible reply',
            'published_at' => now(),
        ]);
        Comment::factory()->for($post)->for($mutedAuthor, 'author')->create([
            'body' => 'Muted reply',
            'published_at' => now()->addMinute(),
        ]);
        $viewer->outgoingRelationships()->create([
            'target_id' => $mutedAuthor->getKey(),
            'type' => 'mute',
        ]);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.canComment', true)
                ->where('posts.0.commentsCount', 2)
                ->has('posts.0.comments', 2)
                ->where('posts.0.comments.0.body', 'First visible reply')
                ->where('posts.0.comments.1.body', 'Second visible reply'));
    }

    public function test_visible_comment_can_be_reported_once_but_not_by_its_author(): void
    {
        Event::fake([CommentReported::class]);

        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->create();
        $post = Post::factory()->for($space)->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();

        $this->actingAs($reporter)
            ->post(route('comments.reports.store', $comment), [
                'reason' => ReportReason::Harassment->value,
                'details' => '  Targets another member.  ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('comment_reports', [
            'space_id' => $space->getKey(),
            'comment_id' => $comment->getKey(),
            'reporter_id' => $reporter->getKey(),
            'reason' => ReportReason::Harassment->value,
            'details' => 'Targets another member.',
            'status' => ReportStatus::Open->value,
        ]);
        Event::assertDispatched(CommentReported::class);

        $this->actingAs($reporter)
            ->post(route('comments.reports.store', $comment), [
                'reason' => ReportReason::Spam->value,
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($author)
            ->post(route('comments.reports.store', $comment), [
                'reason' => ReportReason::Spam->value,
            ])
            ->assertForbidden();
    }

    public function test_comment_reports_join_the_space_queue_and_follow_audited_hide_restore_rules(): void
    {
        Event::fake([CommentReportModerated::class]);

        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->create();
        $space->addMember($reporter);
        $space->addMember($author);
        $post = Post::factory()->for($space)->create(['body' => 'Parent conversation']);
        $comment = Comment::factory()->for($post)->for($author, 'author')->create([
            'body' => 'Reported comment body',
        ]);
        $report = CommentReport::factory()
            ->for($space)
            ->for($comment)
            ->for($reporter, 'reporter')
            ->create();

        $this->actingAs($owner)
            ->get(route('spaces.moderation.index', $space))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.active', 1)
                ->has('reports', 1)
                ->where('reports.0.contentType', 'comment')
                ->where('reports.0.content.body', 'Reported comment body')
                ->where('reports.0.content.postContext.body', 'Parent conversation'));

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.comment-reports.update', [$space, $report]), [
                'action' => ReportAction::Hide->value,
                'note' => 'Breaks the community safety rule.',
            ])
            ->assertRedirect();

        $this->assertNotNull($comment->fresh()->hidden_at);
        $this->assertDatabaseHas('comment_reports', [
            'id' => $report->getKey(),
            'status' => ReportStatus::Resolved->value,
            'reviewed_by' => $owner->getKey(),
        ]);
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'action' => SpaceAuditAction::CommentReportResolved->value,
        ]);
        Event::assertDispatched(CommentReportModerated::class);

        $this->actingAs($reporter)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.commentsCount', 0)
                ->has('posts.0.comments', 0));

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.comment-reports.update', [$space, $report]), [
                'action' => ReportAction::Reopen->value,
                'note' => 'Restored after a second review.',
            ])
            ->assertRedirect();

        $this->assertNull($comment->fresh()->hidden_at);
    }

    public function test_nested_comment_report_binding_cannot_cross_space_boundaries(): void
    {
        $owner = User::factory()->create();
        $first = Space::factory()->for($owner, 'owner')->create();
        $second = Space::factory()->for($owner, 'owner')->create();
        $post = Post::factory()->for($first)->create();
        $comment = Comment::factory()->for($post)->create();
        $report = CommentReport::factory()->for($first)->for($comment)->create();

        $this->actingAs($owner)
            ->patch(route('spaces.moderation.comment-reports.update', [$second, $report]), [
                'action' => ReportAction::Review->value,
            ])
            ->assertNotFound();
    }
}
