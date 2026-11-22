<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SavedCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_create_rename_and_delete_a_private_collection_without_unsaving_posts(): void
    {
        $member = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($member)
            ->post('/saved/collections', ['name' => '   '])
            ->assertSessionHasErrors('name');
        $this->post('/saved/collections', ['name' => str_repeat('a', 81)])
            ->assertSessionHasErrors('name');

        $collection = $this->createCollection('  Research  ');
        $this->get($collection)->assertInertia(fn (Assert $page) => $page
            ->where('savedLibrary.activeCollection.name', 'Research')
            ->has('posts', 0));

        $this->put("/posts/{$post->id}/save")->assertRedirect();
        $this->put("{$collection}/posts/{$post->id}")->assertRedirect();
        $this->patch($collection, ['name' => 'Reference notes'])->assertRedirect($collection);

        $this->get($collection)->assertInertia(fn (Assert $page) => $page
            ->where('savedLibrary.activeCollection.name', 'Reference notes')
            ->where('posts.0.id', $post->id));
        $this->delete($collection)->assertRedirect('/saved');
        $this->get($collection)->assertNotFound();
        $this->get('/saved')->assertInertia(fn (Assert $page) => $page
            ->has('savedLibrary.collections', 0)
            ->where('posts.0.id', $post->id)
            ->has('posts.0.savedCollectionIds', 0));
    }

    public function test_a_post_can_be_in_multiple_collections_and_unsaving_clears_every_assignment(): void
    {
        $member = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($member);
        $first = $this->createCollection('First');
        $second = $this->createCollection('Second');

        $this->put("{$first}/posts/{$post->id}")->assertNotFound();
        $this->put("/posts/{$post->id}/save")->assertRedirect();
        $this->put("{$first}/posts/{$post->id}")->assertRedirect();
        $this->put("{$first}/posts/{$post->id}")->assertRedirect();
        $this->put("{$second}/posts/{$post->id}")->assertRedirect();
        $this->get('/saved')->assertInertia(fn (Assert $page) => $page
            ->where('posts.0.id', $post->id)
            ->has('posts.0.savedCollectionIds', 2));

        $this->delete("{$first}/posts/{$post->id}")->assertRedirect();
        $this->get($first)->assertInertia(fn (Assert $page) => $page->has('posts', 0));
        $this->get($second)->assertInertia(fn (Assert $page) => $page->where('posts.0.id', $post->id));

        $this->delete("/posts/{$post->id}/save")->assertRedirect();
        $this->get($second)->assertInertia(fn (Assert $page) => $page->has('posts', 0));
        $this->put("/posts/{$post->id}/save")->assertRedirect();
        $this->get('/saved')->assertInertia(fn (Assert $page) => $page
            ->where('posts.0.id', $post->id)
            ->has('posts.0.savedCollectionIds', 0));
    }

    public function test_the_collection_limit_is_per_member_and_deleting_a_folder_frees_a_slot(): void
    {
        $member = User::factory()->create();
        DB::table('saved_collections')->insert(array_map(
            fn (int $number): array => ['user_id' => $member->id, 'name' => "Folder {$number}"],
            range(1, 99),
        ));

        $this->actingAs($member);
        $last = $this->createCollection('Last available slot');
        $this->post('/saved/collections', ['name' => 'Too many'])->assertSessionHasErrors('name');

        $this->actingAs(User::factory()->create());
        $this->createCollection('An independent slot');

        $this->actingAs($member)->delete($last)->assertRedirect('/saved');
        $this->createCollection('A freed slot');
    }

    public function test_collection_reads_and_writes_cannot_access_another_members_folders(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create();

        $this->post('/saved/collections', ['name' => 'Not logged in'])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())
            ->post('/saved/collections', ['name' => 'Not verified'])->assertRedirect(route('verification.notice'));

        $this->actingAs($owner);
        $collection = $this->createCollection('Owner only');
        $this->put("/posts/{$post->id}/save")->assertRedirect();
        $this->put("{$collection}/posts/{$post->id}")->assertRedirect();

        $this->actingAs($other)->put("/posts/{$post->id}/save")->assertRedirect();
        $this->get($collection)->assertNotFound();
        $this->patch($collection, ['name' => 'Tampered'])->assertNotFound();
        $this->put("{$collection}/posts/{$post->id}")->assertNotFound();
        $this->delete("{$collection}/posts/{$post->id}")->assertNotFound();
        $this->delete($collection)->assertNotFound();
        $this->get('/saved')->assertInertia(fn (Assert $page) => $page
            ->has('savedLibrary.collections', 0)
            ->has('posts.0.savedCollectionIds', 0));

        $this->actingAs($owner)->get($collection)->assertInertia(fn (Assert $page) => $page
            ->where('savedLibrary.activeCollection.name', 'Owner only')
            ->where('posts.0.id', $post->id));
    }

    public function test_pagination_filters_before_paging_and_breaks_bookmark_timestamp_ties_by_id(): void
    {
        $member = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->create();
        $posts = Post::factory()->count(31)->for($space)->for($author, 'author')->create();
        $hidden = Post::factory()->for($space)->for($author, 'author')->create();

        $this->actingAs($member);
        $collection = $this->createCollection('Reading list');
        $collectionId = (int) basename($collection);
        $now = now();

        foreach ($posts->concat([$hidden]) as $post) {
            DB::table('post_saves')->insert(['user_id' => $member->id, 'post_id' => $post->id, 'created_at' => $now]);
            DB::table('saved_collection_posts')->insert([
                'collection_id' => $collectionId,
                'user_id' => $member->id,
                'post_id' => $post->id,
                'created_at' => $now,
            ]);
        }

        $hidden->update(['hidden_at' => now()]);
        $this->put("{$collection}/posts/{$hidden->id}")->assertNotFound();
        $this->delete("{$collection}/posts/{$hidden->id}")->assertNotFound();

        foreach (['/saved', $collection] as $url) {
            $first = $this->get($url)->assertInertia(fn (Assert $page) => $page
                ->has('posts', 30)
                ->where('posts.0.id', $posts[30]->id)
                ->where('posts.29.id', $posts[1]->id)
                ->where('savedLibrary.pagination.previous', null));
            $next = $first->viewData('page')['props']['savedLibrary']['pagination']['next'];
            $this->assertSame("{$url}?page=2", parse_url($next, PHP_URL_PATH).'?'.parse_url($next, PHP_URL_QUERY));
            $this->get($next)->assertInertia(fn (Assert $page) => $page
                ->has('posts', 1)
                ->where('posts.0.id', $posts[0]->id)
                ->where('savedLibrary.pagination.next', null));
        }
    }

    public function test_export_keeps_owned_assignment_references_without_exposing_post_content(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $visible = Post::factory()->create();
        $hidden = Post::factory()->create();

        $this->actingAs($other);
        $otherCollection = $this->createCollection('Other folder');
        $this->put("/posts/{$visible->id}/save")->assertRedirect();
        $this->put("{$otherCollection}/posts/{$visible->id}")->assertRedirect();

        $this->actingAs($member);
        $collection = $this->createCollection('Member notes');

        foreach ([$visible, $hidden] as $post) {
            $this->put("/posts/{$post->id}/save")->assertRedirect();
            $this->put("{$collection}/posts/{$post->id}")->assertRedirect();
        }

        $hidden->update(['hidden_at' => now()]);

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('personal-data.export'))->assertOk();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(['Member notes'], array_column($export['saved_collections'], 'name'));
        $this->assertSame(array_fill(0, 2, (int) basename($collection)), array_column($export['saved_collection_posts'], 'collection_id'));
        $this->assertSame([$visible->id, $hidden->id], array_column($export['saved_collection_posts'], 'post_id'));
        $this->assertSame(['collection_id', 'post_id', 'created_at'], array_keys($export['saved_collection_posts'][1]));
    }

    private function createCollection(string $name): string
    {
        $response = $this->post('/saved/collections', ['name' => $name])->assertRedirect();
        $location = parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
        $this->assertIsString($location);
        $this->assertMatchesRegularExpression('~^/saved/collections/[0-9]+$~', $location);

        return $location;
    }
}
