<?php

namespace Tests\Feature;

use App\Enums\SpaceRole;
use App\Events\PostPublished;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Space;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        config(['media.disk' => 'media']);
    }

    public function test_verified_members_can_save_private_image_drafts_and_resume_them(): void
    {
        $author = User::factory()->create();
        $moderator = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->private()->create([
            'name' => 'Makers Circle',
        ]);
        $space->addMember($moderator, SpaceRole::Moderator);

        $this->actingAs($author)
            ->post(route('drafts.store'), [
                'body' => '  A private draft for #Laravel makers.  ',
                'space' => $space->slug,
                'image' => UploadedFile::fake()->image('draft.jpg', 1600, 900),
                'image_alt' => '  Makers planning a workshop together.  ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $draft = Post::query()->with('media')->sole();
        $this->assertNull($draft->published_at);
        $this->assertSame('A private draft for #Laravel makers.', $draft->body);
        $this->assertInstanceOf(PostMedia::class, $draft->media);
        $this->assertSame('Makers planning a workshop together.', $draft->media->alt_text);
        Storage::disk('media')->assertExists($draft->media->path);
        $this->assertDatabaseCount('topics', 0);

        $this->actingAs($author)
            ->get(route('drafts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('drafts/index')
                ->has('drafts', 1)
                ->where('drafts.0.id', $draft->getKey())
                ->where('drafts.0.space.name', 'Makers Circle')
                ->where('drafts.0.media.alt', 'Makers planning a workshop together.')
                ->where('draftSummary.count', 1));

        $this->actingAs($author)
            ->get(route('drafts.edit', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('compose/index')
                ->where('draft.id', $draft->getKey())
                ->where('draft.body', 'A private draft for #Laravel makers.')
                ->where('selectedSpace', $space->slug));

        $this->actingAs($author)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts', 0)
                ->where('draftSummary.count', 1));

        $this->actingAs($moderator)
            ->get(route('posts.show', $draft))
            ->assertForbidden();
    }

    public function test_authors_can_replace_remove_and_clean_up_draft_images(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();

        $this->actingAs($author)
            ->post(route('drafts.store'), [
                'body' => 'First version',
                'space' => $space->slug,
                'image' => UploadedFile::fake()->image('first.jpg', 1200, 800),
                'image_alt' => 'The first visual.',
            ])
            ->assertSessionHasNoErrors();

        $draft = Post::query()->with('media')->sole();
        $firstPath = $draft->media?->path;
        $this->assertNotNull($firstPath);

        $this->actingAs($author)
            ->post(route('drafts.update', $draft), [
                '_method' => 'PATCH',
                'body' => 'A much clearer second version',
                'space' => $space->slug,
                'image' => UploadedFile::fake()->image('second.png', 900, 900),
                'image_alt' => 'The replacement square visual.',
                'remove_image' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Draft updated privately.');

        $draft->refresh()->load('media');
        $secondPath = $draft->media?->path;
        $this->assertNotNull($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
        $this->assertSame('A much clearer second version', $draft->body);
        $this->assertSame('The replacement square visual.', $draft->media?->alt_text);
        Storage::disk('media')->assertMissing($firstPath);
        Storage::disk('media')->assertExists($secondPath);

        $this->actingAs($author)
            ->patch(route('drafts.update', $draft), [
                'body' => 'Text-only version',
                'space' => $space->slug,
                'remove_image' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('post_media', ['post_id' => $draft->getKey()]);
        Storage::disk('media')->assertMissing($secondPath);

        $this->actingAs($author)
            ->delete(route('drafts.destroy', $draft))
            ->assertRedirect(route('drafts.index'));

        $this->assertDatabaseMissing('posts', ['id' => $draft->getKey()]);
    }

    public function test_publishing_a_draft_keeps_its_identity_and_dispatches_publication_once(): void
    {
        Event::fake([PostPublished::class]);

        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $draft = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'Early wording',
            'published_at' => null,
        ]);

        $this->actingAs($author)
            ->post(route('drafts.publish', $draft), [
                'body' => 'The finished #Laravel release note.',
                'space' => $space->slug,
                'remove_image' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('posts.show', $draft))
            ->assertSessionHas('status', 'Post published.');

        $draft->refresh();
        $this->assertNotNull($draft->published_at);
        $this->assertSame('The finished #Laravel release note.', $draft->body);
        $this->assertTrue(
            $draft->topics()->where('name', 'laravel')->exists(),
        );
        $this->assertSame(1, Topic::query()->count());

        Event::assertDispatchedTimes(PostPublished::class, 1);
        Event::assertDispatched(
            PostPublished::class,
            fn (PostPublished $event): bool => $event->post->is($draft),
        );

        $this->actingAs($author)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $draft->getKey())
                ->where('draftSummary.count', 0));
    }

    public function test_drafts_cannot_be_read_or_mutated_by_other_members_or_moderators(): void
    {
        $author = User::factory()->create();
        $moderator = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->private()->create();
        $space->addMember($moderator, SpaceRole::Moderator);
        $draft = Post::factory()->for($space)->for($author, 'author')->create([
            'published_at' => null,
        ]);

        $this->actingAs($moderator)
            ->get(route('drafts.edit', $draft))
            ->assertForbidden();
        $this->actingAs($moderator)
            ->patch(route('drafts.update', $draft), [
                'body' => 'Changed by someone else',
                'space' => $space->slug,
            ])
            ->assertForbidden();
        $this->actingAs($moderator)
            ->post(route('drafts.publish', $draft), [
                'body' => 'Published by someone else',
                'space' => $space->slug,
            ])
            ->assertForbidden();
        $this->actingAs($moderator)
            ->delete(route('drafts.destroy', $draft))
            ->assertForbidden();

        $this->assertNull($draft->fresh()?->published_at);
        $this->assertNotSame('Changed by someone else', $draft->fresh()?->body);
    }

    public function test_draft_actions_require_verified_active_membership_and_valid_content(): void
    {
        $member = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($member, 'owner')->create();

        $this->post(route('drafts.store'), [
            'body' => 'Guest draft',
            'space' => $space->slug,
        ])->assertRedirect(route('login'));

        $this->actingAs($unverified)
            ->post(route('drafts.store'), [
                'body' => 'Unverified draft',
                'space' => $space->slug,
            ])
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($outsider)
            ->post(route('drafts.store'), [
                'body' => 'Outsider draft',
                'space' => $space->slug,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('drafts.store'), [
                'body' => '',
                'space' => $space->slug,
            ])
            ->assertSessionHasErrors('body');

        $this->actingAs($member)
            ->post(route('drafts.store'), [
                'body' => 'Missing alternative text',
                'space' => $space->slug,
                'image' => UploadedFile::fake()->image('draft.jpg'),
            ])
            ->assertSessionHasErrors('image_alt');

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_media', 0);
    }

    public function test_authors_must_move_a_draft_after_losing_access_to_its_space(): void
    {
        $author = User::factory()->create();
        $formerSpace = Space::factory()->create();
        $currentSpace = Space::factory()->for($author, 'owner')->create();
        $formerSpace->addMember($author);

        $this->actingAs($author)
            ->post(route('drafts.store'), [
                'body' => 'A draft that needs a new community.',
                'space' => $formerSpace->slug,
                'image' => UploadedFile::fake()->image('move.jpg', 1200, 800),
                'image_alt' => 'A community planning board.',
            ])
            ->assertSessionHasNoErrors();

        $draft = Post::query()->with('media')->sole();

        $formerSpace->members()->detach($author);

        $this->actingAs($author)
            ->get(route('drafts.edit', $draft))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedSpace', $currentSpace->slug)
                ->has('spaces', 1)
                ->where('spaces.0.slug', $currentSpace->slug)
                ->where('draft.media.alt', 'A community planning board.'));

        $this->actingAs($author)
            ->get(route('posts.image', $draft))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');

        $this->actingAs($author)
            ->post(route('drafts.publish', $draft), [
                'body' => 'Cannot publish back into the former Space.',
                'space' => $formerSpace->slug,
            ])
            ->assertForbidden();

        $this->actingAs($author)
            ->patch(route('drafts.update', $draft), [
                'body' => 'Moved into a Space where the author can post.',
                'space' => $currentSpace->slug,
                'image_alt' => 'A community planning board.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($currentSpace->getKey(), $draft->fresh()?->space_id);
    }

    public function test_members_cannot_exceed_the_bounded_private_draft_limit(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();

        Post::factory()
            ->count(50)
            ->for($space)
            ->for($author, 'author')
            ->create(['published_at' => null]);

        $this->actingAs($author)
            ->post(route('drafts.store'), [
                'body' => 'One draft too many',
                'space' => $space->slug,
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(
            50,
            Post::query()
                ->whereBelongsTo($author, 'author')
                ->whereNull('published_at')
                ->count(),
        );
    }
}
