<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthoredContentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_edit_a_post_and_the_ui_receives_an_explicit_edited_state(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($author);
        $post = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'Original post',
        ]);

        $this->actingAs($author)
            ->patch(route('posts.update', $post), ['body' => '  Improved post copy.  '])
            ->assertRedirect()
            ->assertSessionHas('status', 'Post updated.');

        $post->refresh();

        $this->assertSame('Improved post copy.', $post->body);
        $this->assertNotNull($post->edited_at);

        $this->actingAs($author)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.body', 'Improved post copy.')
                ->where('posts.0.canEdit', true)
                ->where('posts.0.canDelete', true)
                ->where('posts.0.editedAt', $post->edited_at?->toIso8601String()));
    }

    public function test_author_can_edit_a_comment_and_the_conversation_receives_author_controls(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($author);
        $post = Post::factory()->for($space)->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create([
            'body' => 'Original reply',
        ]);

        $this->actingAs($author)
            ->patch(route('comments.update', $comment), ['body' => '  Clearer reply.  '])
            ->assertRedirect()
            ->assertSessionHas('status', 'Comment updated.');

        $comment->refresh();

        $this->assertSame('Clearer reply.', $comment->body);
        $this->assertNotNull($comment->edited_at);

        $this->actingAs($author)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('comments.data.0.body', 'Clearer reply.')
                ->where('comments.data.0.canEdit', true)
                ->where('comments.data.0.canDelete', true)
                ->where('comments.data.0.editedAt', $comment->edited_at?->toIso8601String()));
    }

    public function test_members_cannot_change_or_delete_content_authored_by_someone_else(): void
    {
        $author = User::factory()->create();
        $otherMember = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($author);
        $space->addMember($otherMember);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();

        $this->actingAs($otherMember)
            ->patch(route('posts.update', $post), ['body' => 'Unauthorized edit'])
            ->assertForbidden();
        $this->actingAs($otherMember)
            ->delete(route('posts.destroy', $post))
            ->assertForbidden();
        $this->actingAs($otherMember)
            ->patch(route('comments.update', $comment), ['body' => 'Unauthorized edit'])
            ->assertForbidden();
        $this->actingAs($otherMember)
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->getKey()]);
        $this->assertDatabaseHas('comments', ['id' => $comment->getKey()]);
    }

    public function test_active_moderation_reports_lock_author_edits_and_deletions(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($author);
        $space->addMember($reporter);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $comment = Comment::factory()->for($post)->for($author, 'author')->create();

        PostReport::factory()
            ->for($space)
            ->for($post)
            ->for($reporter, 'reporter')
            ->create(['status' => ReportStatus::Reviewing]);
        CommentReport::factory()
            ->for($space)
            ->for($comment)
            ->for($reporter, 'reporter')
            ->create(['status' => ReportStatus::Open]);

        $this->actingAs($author)
            ->patch(route('posts.update', $post), ['body' => 'Changed while under review'])
            ->assertSessionHasErrors('content');
        $this->actingAs($author)
            ->delete(route('posts.destroy', $post))
            ->assertSessionHasErrors('content');
        $this->actingAs($author)
            ->patch(route('comments.update', $comment), ['body' => 'Changed while under review'])
            ->assertSessionHasErrors('content');
        $this->actingAs($author)
            ->delete(route('comments.destroy', $comment))
            ->assertSessionHasErrors('content');

        $this->actingAs($author)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.canEdit', false)
                ->where('posts.0.canDelete', false)
                ->where('posts.0.comments.0.canEdit', false)
                ->where('posts.0.comments.0.canDelete', false));
    }

    public function test_author_can_delete_unreported_content_and_dismissed_reports_do_not_keep_it_locked(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($author);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $firstComment = Comment::factory()->for($post)->for($author, 'author')->create();
        $secondComment = Comment::factory()->for($post)->for($author, 'author')->create();

        CommentReport::factory()
            ->for($space)
            ->for($firstComment)
            ->for($reporter, 'reporter')
            ->create(['status' => ReportStatus::Dismissed]);

        $this->actingAs($author)
            ->delete(route('comments.destroy', $firstComment))
            ->assertRedirect()
            ->assertSessionHas('status', 'Comment deleted.');

        $this->assertDatabaseMissing('comments', ['id' => $firstComment->getKey()]);
        $this->assertDatabaseHas('comments', ['id' => $secondComment->getKey()]);

        $this->actingAs($author)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('feed'))
            ->assertSessionHas('status', 'Post deleted.');

        $this->assertDatabaseMissing('posts', ['id' => $post->getKey()]);
        $this->assertDatabaseMissing('comments', ['id' => $secondComment->getKey()]);
    }
}
