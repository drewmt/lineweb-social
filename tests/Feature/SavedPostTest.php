<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SavedPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_posts_require_a_verified_member(): void
    {
        $post = Post::factory()->create();

        $this->get(route('saved.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->put(route('posts.saves.store', $post))
            ->assertRedirect(route('verification.notice'));

        $this->assertDatabaseEmpty('post_saves');
    }

    public function test_a_visible_post_can_be_saved_idempotently_and_exposed_to_its_viewer(): void
    {
        $viewer = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($viewer)
            ->from(route('feed'))
            ->put(route('posts.saves.store', $post))
            ->assertRedirect(route('feed'))
            ->assertSessionHas('status', 'Post saved for later.');

        $this->actingAs($viewer)
            ->put(route('posts.saves.store', $post))
            ->assertRedirect();

        $this->assertDatabaseCount('post_saves', 1);
        $this->assertDatabaseHas('post_saves', [
            'user_id' => $viewer->getKey(),
            'post_id' => $post->getKey(),
        ]);

        $this->actingAs($viewer)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('posts.0.id', $post->getKey())
                ->where('posts.0.isSaved', true));
    }

    public function test_draft_hidden_and_inaccessible_private_posts_cannot_be_saved(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $publicSpace = Space::factory()->create();
        $privateSpace = Space::factory()->private()->create();
        $draft = Post::factory()->for($publicSpace)->for($author, 'author')->create([
            'published_at' => null,
        ]);
        $hidden = Post::factory()->for($publicSpace)->for($author, 'author')->create([
            'hidden_at' => now(),
        ]);
        $private = Post::factory()->for($privateSpace)->for($author, 'author')->create();

        foreach ([$draft, $hidden, $private] as $post) {
            $this->actingAs($viewer)
                ->put(route('posts.saves.store', $post))
                ->assertForbidden();
        }

        $this->assertDatabaseEmpty('post_saves');
    }

    public function test_removing_a_save_only_changes_the_current_members_library(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create();

        DB::table('post_saves')->insert([
            [
                'user_id' => $viewer->getKey(),
                'post_id' => $post->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $other->getKey(),
                'post_id' => $post->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($viewer)
            ->from(route('saved.index'))
            ->delete(route('posts.saves.destroy', $post))
            ->assertRedirect(route('saved.index'))
            ->assertSessionHas('status', 'Post removed from saved.');

        $this->assertDatabaseMissing('post_saves', [
            'user_id' => $viewer->getKey(),
            'post_id' => $post->getKey(),
        ]);
        $this->assertDatabaseHas('post_saves', [
            'user_id' => $other->getKey(),
            'post_id' => $post->getKey(),
        ]);
    }

    public function test_saved_posts_are_private_ordered_and_reapply_current_visibility(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->create();
        $older = Post::factory()->for($space)->create(['body' => 'Saved first']);
        $newer = Post::factory()->for($space)->create(['body' => 'Saved second']);
        $hidden = Post::factory()->for($space)->create([
            'body' => 'No longer visible',
            'hidden_at' => now(),
        ]);
        $otherPost = Post::factory()->for($space)->create(['body' => 'Someone else saved this']);

        DB::table('post_saves')->insert([
            [
                'user_id' => $viewer->getKey(),
                'post_id' => $older->getKey(),
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'user_id' => $viewer->getKey(),
                'post_id' => $newer->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $viewer->getKey(),
                'post_id' => $hidden->getKey(),
                'created_at' => now()->addMinute(),
                'updated_at' => now()->addMinute(),
            ],
            [
                'user_id' => $other->getKey(),
                'post_id' => $otherPost->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($viewer)
            ->get(route('saved.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('feed/index')
                ->where('viewMode', 'saved')
                ->has('posts', 2)
                ->where('posts.0.body', 'Saved second')
                ->where('posts.0.isSaved', true)
                ->where('posts.1.body', 'Saved first')
                ->where('posts.1.isSaved', true));
    }

    public function test_post_permalink_exposes_only_the_current_members_save_state(): void
    {
        $viewer = User::factory()->create();
        $post = Post::factory()->create();

        DB::table('post_saves')->insert([
            'user_id' => $viewer->getKey(),
            'post_id' => $post->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.id', $post->getKey())
                ->where('post.isSaved', true));

        $this->actingAs(User::factory()->create())
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.isSaved', false));
    }
}
