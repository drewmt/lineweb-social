<?php

namespace Tests\Feature;

use App\Community\PublishStory;
use App\Enums\SpaceAuditAction;
use App\Enums\SpaceRole;
use App\Enums\UserRelationshipType;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\Story;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        config(['media.disk' => 'media']);
    }

    public function test_space_members_can_publish_a_text_story_for_exactly_twenty_four_hours(): void
    {
        $this->travelTo(now()->startOfMinute());
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->private()->create();

        $this->actingAs($author)
            ->post(route('spaces.stories.store', $space), [
                'body' => '  Studio doors are open tonight.  ',
                'background' => 'violet',
            ])
            ->assertSessionHasNoErrors();

        $story = Story::query()->sole();

        $this->assertSame('Studio doors are open tonight.', $story->body);
        $this->assertSame('violet', $story->background);
        $this->assertTrue($story->expires_at->equalTo(now()->addHours(24)));

        $this->actingAs($author)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stories.0.id', $story->getKey())
                ->where('stories.0.body', 'Studio doors are open tonight.')
                ->where('stories.0.image', null)
                ->missing('stories.0.disk')
                ->missing('stories.0.path'));
    }

    public function test_story_requires_content_membership_and_an_allowed_background(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->private()->create();

        $this->actingAs($owner)
            ->post(route('spaces.stories.store', $space), [
                'body' => '',
                'background' => 'metallic',
            ])
            ->assertSessionHasErrors(['body', 'background']);

        $this->actingAs($outsider)
            ->post(route('spaces.stories.store', $space), [
                'body' => 'I am not a member.',
                'background' => 'ink',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('stories', 0);
    }

    public function test_publisher_rechecks_current_space_membership_inside_the_transaction(): void
    {
        $owner = User::factory()->create();
        $formerMember = User::factory()->create();
        $space = Space::factory()->for($owner, 'owner')->private()->create();

        $this->expectException(AuthorizationException::class);

        app(PublishStory::class)->publish(
            $formerMember,
            $space,
            'This should not be published.',
            'ink',
            null,
            '',
        );
    }

    public function test_active_story_limit_is_enforced_per_author_and_space(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();

        Story::query()->insert(collect(range(1, Story::ACTIVE_LIMIT_PER_SPACE))
            ->map(fn (int $number): array => [
                'space_id' => $space->getKey(),
                'user_id' => $author->getKey(),
                'body' => "Story {$number}",
                'background' => 'ink',
                'expires_at' => now()->addHour(),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all());

        $this->actingAs($author)
            ->post(route('spaces.stories.store', $space), [
                'body' => 'One too many',
                'background' => 'ocean',
            ])
            ->assertSessionHasErrors(['space']);

        $this->assertDatabaseCount('stories', Story::ACTIVE_LIMIT_PER_SPACE);
    }

    public function test_story_images_are_normalized_private_and_rechecked_on_delivery(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->private()->create();
        $space->addMember($member);
        $upload = UploadedFile::fake()->image('phone-photo.jpg', 2400, 1200);

        $this->actingAs($author)
            ->post(route('spaces.stories.store', $space), [
                'body' => 'A look inside the workshop.',
                'background' => 'mint',
                'image' => $upload,
                'alt_text' => '  Members working around a large wooden table.  ',
            ])
            ->assertSessionHasNoErrors();

        $story = Story::query()->sole();

        $this->assertSame('image/webp', $story->mime_type);
        $this->assertSame(2048, $story->width);
        $this->assertSame(1024, $story->height);
        $this->assertSame('Members working around a large wooden table.', $story->alt_text);
        $this->assertMatchesRegularExpression(
            '#^stories/\d{4}/\d{2}/[0-9a-f-]{36}\.webp$#',
            (string) $story->path,
        );
        Storage::disk('media')->assertExists((string) $story->path);

        $this->actingAs($member)
            ->get(route('stories.image', $story))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Cache-Control', 'max-age=900, private');

        $this->actingAs($outsider)
            ->get(route('stories.image', $story))
            ->assertForbidden();
    }

    public function test_blocked_members_do_not_see_or_open_each_others_stories(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($viewer);
        $story = Story::query()->create([
            'space_id' => $space->getKey(),
            'user_id' => $author->getKey(),
            'body' => 'Hidden by the safety boundary.',
            'background' => 'ink',
            'expires_at' => now()->addHour(),
        ]);
        UserRelationship::query()->create([
            'actor_id' => $viewer->getKey(),
            'target_id' => $author->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stories', []));

        $this->actingAs($viewer)
            ->get(route('stories.show', $story))
            ->assertForbidden();
    }

    public function test_expired_stories_are_hidden_then_pruned_with_their_media(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        Storage::disk('media')->put('stories/expired.webp', 'expired-image');
        $story = Story::query()->create([
            'space_id' => $space->getKey(),
            'user_id' => $author->getKey(),
            'body' => 'Already gone.',
            'background' => 'sunset',
            'disk' => 'media',
            'path' => 'stories/expired.webp',
            'mime_type' => 'image/webp',
            'width' => 100,
            'height' => 160,
            'size_bytes' => 13,
            'checksum' => hash('sha256', 'expired-image'),
            'expires_at' => now()->subSecond(),
        ]);

        $this->actingAs($author)
            ->get(route('feed'))
            ->assertInertia(fn (Assert $page) => $page->where('stories', []));
        $this->actingAs($author)
            ->get(route('stories.show', $story))
            ->assertNotFound();

        $this->artisan('stories:prune')
            ->expectsOutput('Pruned 1 expired Story record(s).')
            ->assertSuccessful();

        $this->assertModelMissing($story);
        Storage::disk('media')->assertMissing('stories/expired.webp');
    }

    public function test_authors_and_space_moderators_can_remove_stories_but_other_members_cannot(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $moderator = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $space->addMember($member);
        $space->addMember($moderator, SpaceRole::Moderator);
        Storage::disk('media')->put('stories/delete-me.webp', 'image');
        $story = Story::query()->create([
            'space_id' => $space->getKey(),
            'user_id' => $author->getKey(),
            'body' => 'Temporary.',
            'background' => 'ocean',
            'disk' => 'media',
            'path' => 'stories/delete-me.webp',
            'mime_type' => 'image/webp',
            'width' => 100,
            'height' => 160,
            'size_bytes' => 5,
            'checksum' => hash('sha256', 'image'),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($member)
            ->get(route('stories.show', $story))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('story.canDelete', false)
                ->where('story.ownedByViewer', false));

        $this->actingAs($member)
            ->delete(route('stories.destroy', $story))
            ->assertForbidden();

        $this->actingAs($moderator)
            ->get(route('stories.show', $story))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('story.canDelete', true)
                ->where('story.ownedByViewer', false));

        $this->actingAs($moderator)
            ->delete(route('stories.destroy', $story))
            ->assertRedirect(route('feed'));

        $this->assertModelMissing($story);
        Storage::disk('media')->assertMissing('stories/delete-me.webp');
        $this->assertDatabaseHas('space_audit_logs', [
            'space_id' => $space->getKey(),
            'actor_id' => $moderator->getKey(),
            'subject_user_id' => $author->getKey(),
            'action' => SpaceAuditAction::StoryRemoved->value,
        ]);
        $audit = SpaceAuditLog::query()->sole();
        $this->assertSame(['story_id' => $story->getKey()], $audit->context);
        $this->assertArrayNotHasKey('body', $audit->context);

        $ownStory = Story::query()->create([
            'space_id' => $space->getKey(),
            'user_id' => $author->getKey(),
            'body' => 'My own Story.',
            'background' => 'mint',
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($author)
            ->delete(route('stories.destroy', $ownStory))
            ->assertRedirect(route('feed'));

        $this->assertModelMissing($ownStory);
        $this->assertDatabaseCount('space_audit_logs', 1);
    }

    public function test_personal_export_includes_safe_active_story_metadata_only(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        Story::query()->create([
            'space_id' => $space->getKey(),
            'user_id' => $author->getKey(),
            'body' => 'Exported moment.',
            'background' => 'mint',
            'disk' => 'media',
            'path' => 'stories/private-path.webp',
            'mime_type' => 'image/webp',
            'width' => 100,
            'height' => 160,
            'size_bytes' => 42,
            'checksum' => str_repeat('a', 64),
            'alt_text' => 'Safe description',
            'expires_at' => now()->addHour(),
        ]);
        Story::query()->create([
            'space_id' => $space->getKey(),
            'user_id' => $author->getKey(),
            'body' => 'Expired moment.',
            'background' => 'ink',
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($author)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('personal-data.export'))
            ->assertOk();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertCount(1, $export['active_stories']);
        $this->assertSame('Exported moment.', $export['active_stories'][0]['body']);
        $this->assertSame('image/webp', $export['active_stories'][0]['media']['mime_type']);
        $this->assertArrayNotHasKey('path', $export['active_stories'][0]);
        $this->assertArrayNotHasKey('checksum', $export['active_stories'][0]);
    }
}
