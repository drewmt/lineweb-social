<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Events\PostPublished;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        config(['media.disk' => 'media']);
    }

    public function test_members_can_publish_a_normalized_private_image_with_safe_public_metadata(): void
    {
        Event::fake([PostPublished::class]);

        $author = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $space = Space::factory()->for($author, 'owner')->private()->create();
        $upload = UploadedFile::fake()->image('customer-location.jpg', 2600, 1300);
        $marker = 'ORIGINAL-PRIVATE-METADATA';

        file_put_contents($upload->getRealPath(), $marker, FILE_APPEND);

        $this->actingAs($author)
            ->from(route('spaces.show', $space))
            ->post(route('spaces.posts.store', $space), [
                'body' => '  A workshop update with an image.  ',
                'image' => $upload,
                'image_alt' => '  A bright workshop with tools on a long table.  ',
            ])
            ->assertRedirect(route('spaces.show', $space))
            ->assertSessionHasNoErrors();

        $post = Post::query()->with('media')->sole();
        $media = $post->media;

        $this->assertInstanceOf(PostMedia::class, $media);
        $this->assertSame('A workshop update with an image.', $post->body);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame(2048, $media->width);
        $this->assertSame(1024, $media->height);
        $this->assertSame('A bright workshop with tools on a long table.', $media->alt_text);
        $this->assertMatchesRegularExpression(
            '#^posts/\d{4}/\d{2}/[0-9a-f-]{36}\.webp$#',
            $media->path,
        );
        $this->assertStringNotContainsString('customer-location', $media->path);

        Storage::disk('media')->assertExists($media->path);
        $stored = Storage::disk('media')->get($media->path);

        $this->assertSame('RIFF', substr($stored, 0, 4));
        $this->assertSame('WEBP', substr($stored, 8, 4));
        $this->assertStringNotContainsString($marker, $stored);
        $this->assertSame(hash('sha256', $stored), $media->checksum);
        $this->assertSame(strlen($stored), $media->size_bytes);

        Event::assertDispatched(
            PostPublished::class,
            fn (PostPublished $event): bool => $event->post->is($post),
        );

        $expectedMedia = [
            'url' => route('posts.image', $post),
            'alt' => 'A bright workshop with tools on a long table.',
            'width' => 2048,
            'height' => 1024,
        ];

        $this->actingAs($author)
            ->get(route('feed', ['space' => $space->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.media', $expectedMedia)
                ->missing('posts.0.media.path')
                ->missing('posts.0.media.disk')
                ->missing('posts.0.media.checksum'));

        $this->actingAs($author)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('post.media', $expectedMedia));

        $this->actingAs($author)
            ->get(route('people.show', $author))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('posts.0.media', $expectedMedia));
    }

    public function test_upload_validation_rejects_missing_alt_text_invalid_content_and_oversized_files(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => 'Missing alternative text.',
                'image' => UploadedFile::fake()->image('photo.jpg', 640, 480),
            ])
            ->assertSessionHasErrors('image_alt');

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => 'A file pretending to be an image.',
                'image' => UploadedFile::fake()->createWithContent(
                    'payload.jpg',
                    '<?php echo "not an image"; ?>',
                ),
                'image_alt' => 'This should never be stored.',
            ])
            ->assertSessionHasErrors('image');

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => 'An oversized file.',
                'image' => UploadedFile::fake()->create('large.jpg', 8193, 'image/jpeg'),
                'image_alt' => 'This file is larger than the upload limit.',
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_media', 0);
        Storage::disk('media')->assertDirectoryEmpty('/');
    }

    public function test_decoded_pixel_limit_is_enforced_before_a_post_or_file_is_created(): void
    {
        config(['media.max_source_pixels' => 100]);

        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => 'A deceptively small compressed image.',
                'image' => UploadedFile::fake()->image('pixels.jpg', 20, 20),
                'image_alt' => 'A test grid.',
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('post_media', 0);
        Storage::disk('media')->assertDirectoryEmpty('/');
    }

    public function test_private_image_delivery_reuses_post_visibility_and_safety_policies(): void
    {
        $author = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $space = Space::factory()->for($author, 'owner')->private()->create();
        $space->addMember($member);
        $post = Post::factory()->for($space)->for($author, 'author')->create();
        $media = $this->attachMedia($post);

        $this->get(route('posts.image', $post))->assertRedirect(route('login'));

        $this->actingAs($unverified)
            ->get(route('posts.image', $post))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($outsider)->get(route('posts.image', $post))->assertForbidden();

        $response = $this->actingAs($member)->get(route('posts.image', $post));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Content-Disposition', 'inline; filename="post-image.webp"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
            ->assertHeader('ETag', '"'.$media->checksum.'"')
            ->assertContent($this->imageContents());
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', (string) $response->headers->get('Cache-Control'));

        $post->update(['hidden_at' => now()]);

        $this->actingAs($member)->get(route('posts.image', $post))->assertForbidden();
        $this->actingAs($author)->get(route('posts.image', $post))->assertOk();

        $post->update(['hidden_at' => null]);
        $member->outgoingRelationships()->create([
            'target_id' => $author->getKey(),
            'type' => 'block',
        ]);

        $this->actingAs($member)->get(route('posts.image', $post))->assertForbidden();

        Storage::disk('media')->delete($media->path);

        $this->actingAs($author)->get(route('posts.image', $post))->assertNotFound();
    }

    public function test_media_objects_are_removed_when_posts_spaces_or_accounts_are_deleted(): void
    {
        $firstAuthor = User::factory()->create();
        $firstSpace = Space::factory()->for($firstAuthor, 'owner')->create();
        $directPost = Post::factory()->for($firstSpace)->for($firstAuthor, 'author')->create();
        $directMedia = $this->attachMedia($directPost, 'posts/direct.webp');

        $directPost->delete();

        Storage::disk('media')->assertMissing($directMedia->path);

        $spaceOwner = User::factory()->create();
        $guestAuthor = User::factory()->create();
        $deletedSpace = Space::factory()->for($spaceOwner, 'owner')->create();
        $guestPost = Post::factory()->for($deletedSpace)->for($guestAuthor, 'author')->create();
        $spaceMedia = $this->attachMedia($guestPost, 'posts/space.webp');

        $deletedSpace->delete();

        Storage::disk('media')->assertMissing($spaceMedia->path);

        $account = User::factory()->create();
        $externalOwner = User::factory()->create();
        $externalSpace = Space::factory()->for($externalOwner, 'owner')->create();
        $authoredPost = Post::factory()->for($externalSpace)->for($account, 'author')->create();
        $authoredMedia = $this->attachMedia($authoredPost, 'posts/account-authored.webp');
        $ownedSpace = Space::factory()->for($account, 'owner')->create();
        $ownedPost = Post::factory()->for($ownedSpace)->for($guestAuthor, 'author')->create();
        $ownedMedia = $this->attachMedia($ownedPost, 'posts/account-owned.webp');

        $account->delete();

        Storage::disk('media')->assertMissing($authoredMedia->path);
        Storage::disk('media')->assertMissing($ownedMedia->path);
        $this->assertDatabaseCount('post_media', 0);
    }

    private function attachMedia(Post $post, ?string $path = null): PostMedia
    {
        $contents = $this->imageContents();
        $path ??= 'posts/'.$post->getKey().'.webp';

        Storage::disk('media')->put($path, $contents);

        return $post->media()->create([
            'disk' => 'media',
            'path' => $path,
            'mime_type' => 'image/webp',
            'width' => 640,
            'height' => 360,
            'size_bytes' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'alt_text' => 'A test community image.',
        ]);
    }

    private function imageContents(): string
    {
        return 'RIFF'.pack('V', 4).'WEBP';
    }
}
