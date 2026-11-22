<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Post;
use App\Models\Space;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonalFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_owner_can_list_open_edit_or_delete_a_named_feed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->get('/my-feeds')->assertRedirect(route('login'));
        $url = $this->actingAs($owner)->makeFeed(['name' => ' Studio reads ']);

        $this->get('/my-feeds')->assertInertia(fn (Assert $page) => $page
            ->has('feeds', 1)->where('feeds.0.name', 'Studio reads'));
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('feed.name', 'Studio reads')->has('posts', 0));
        $this->get($url.'/edit')->assertOk();

        $this->actingAs($other)->get('/my-feeds')->assertInertia(fn (Assert $page) => $page->has('feeds', 0));
        $this->get($url)->assertNotFound();
        $this->get($url.'/edit')->assertNotFound();
        $this->patch($url, ['name' => 'Changed'])->assertNotFound();
        $this->delete($url)->assertNotFound();

        $this->actingAs($owner)->patch($url, ['name' => 'Editorial reads', 'sources' => []])->assertRedirect($url);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('feed.name', 'Editorial reads'));
        $this->delete($url)->assertRedirect('/my-feeds');
        $this->get($url)->assertNotFound();
    }

    public function test_visible_source_matches_are_deduplicated_and_have_a_stable_chronological_order(): void
    {
        $viewer = User::factory()->create();
        $person = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $space = Space::factory()->create();
        $other = Space::factory()->create();
        $topic = Topic::query()->create(['name' => 'studio']);
        $time = now()->subMinute();
        $old = Post::factory()->for($other)->for($person, 'author')->create(['published_at' => $time->copy()->subMinute()]);
        $firstTie = Post::factory()->for($space)->create(['published_at' => $time]);
        $all = Post::factory()->for($space)->for($person, 'author')->create(['published_at' => $time]);
        $fromTopic = Post::factory()->for($other)->create(['published_at' => $time]);
        $all->topics()->attach($topic);
        $fromTopic->topics()->attach($topic);
        Post::factory()->for($other)->create();

        $url = $this->actingAs($viewer)->makeFeed(['sources' => [
            'people' => [$person->id], 'spaces' => [$space->id], 'topics' => [$topic->id],
        ]]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('posts', 4)
            ->where('posts.0.id', $fromTopic->id)->where('posts.1.id', $all->id)
            ->where('posts.2.id', $firstTie->id)->where('posts.3.id', $old->id));
        $this->get('/favorites')->assertInertia(fn (Assert $page) => $page->has('posts', 0));
        $this->get('/following')->assertInertia(fn (Assert $page) => $page->has('posts', 0));
    }

    public function test_inaccessible_sources_cannot_be_discovered_or_added(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create(['name' => 'Studio private', 'profile_visibility' => ProfileVisibility::Private]);
        $undiscoverable = User::factory()->create(['name' => 'Studio unlisted', 'is_discoverable' => false, 'profile_visibility' => ProfileVisibility::Public]);
        $blocked = User::factory()->create(['name' => 'Studio blocked', 'profile_visibility' => ProfileVisibility::Public]);
        $blocked->outgoingRelationships()->create(['target_id' => $viewer->id, 'type' => UserRelationshipType::Block]);
        $public = User::factory()->create(['name' => 'Studio public', 'profile_visibility' => ProfileVisibility::Public]);
        $space = Space::factory()->hidden()->create(['name' => 'Studio internal']);
        $topic = Topic::query()->create(['name' => 'studiointernal']);
        Post::factory()->for($space)->create()->topics()->attach($topic);

        $this->actingAs($viewer)->getJson('/my-feeds/sources?q=studio')->assertOk()
            ->assertJsonCount(1, 'people')->assertJsonPath('people.0.id', $public->id)
            ->assertJsonCount(0, 'spaces')->assertJsonCount(0, 'topics');
        foreach ([
            ['people' => [$profile->id]], ['people' => [$undiscoverable->id]], ['people' => [$blocked->id]],
            ['spaces' => [$space->id]], ['topics' => [$topic->id]], ['people' => [999999999]],
        ] as $sources) {
            $this->postJson('/my-feeds', ['name' => 'Unavailable', 'sources' => $sources])
                ->assertUnprocessable()->assertJsonValidationErrors('sources');
        }
        $this->get('/my-feeds')->assertInertia(fn (Assert $page) => $page->has('feeds', 0));
    }

    public function test_revoked_sources_and_restricted_posts_disappear_from_the_feed_and_editor(): void
    {
        $viewer = User::factory()->create();
        $person = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $muted = User::factory()->create();
        $blocked = User::factory()->create();
        $public = Space::factory()->create();
        $private = Space::factory()->private()->create();
        $other = Space::factory()->create();
        $private->addMember($viewer);
        $topic = Topic::query()->create(['name' => 'confidential']);
        $hiddenTopicPost = Post::factory()->for($private)->create();
        $hiddenTopicPost->topics()->attach($topic);
        Post::factory()->for($other)->for($person, 'author')->create();
        $allowed = Post::factory()->for($public)->create();
        Post::factory()->for($public)->for($muted, 'author')->create();
        Post::factory()->for($public)->for($blocked, 'author')->create();
        Post::factory()->for($public)->create(['published_at' => null]);
        Post::factory()->for($public)->create(['hidden_at' => now()]);
        $viewer->outgoingRelationships()->create(['target_id' => $muted->id, 'type' => UserRelationshipType::Mute]);
        $blocked->outgoingRelationships()->create(['target_id' => $viewer->id, 'type' => UserRelationshipType::Block]);

        $url = $this->actingAs($viewer)->makeFeed(['sources' => [
            'people' => [$person->id], 'spaces' => [$private->id, $public->id], 'topics' => [$topic->id],
        ]]);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->has('posts', 3));
        $private->members()->detach($viewer);
        $person->update(['profile_visibility' => ProfileVisibility::Private]);

        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->has('posts', 1)->where('posts.0.id', $allowed->id));
        $this->get($url.'/edit')->assertInertia(fn (Assert $page) => $page
            ->has('selectedSources.people', 0)->has('selectedSources.topics', 0)
            ->has('selectedSources.spaces', 1)->where('selectedSources.spaces.0.id', $public->id));
        $this->patchJson($url, ['name' => 'Do not persist', 'sources' => ['spaces' => [$private->id]]])
            ->assertUnprocessable()->assertJsonValidationErrors('sources');
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('feed.name', 'My reading'));
        $this->patch($url, ['name' => 'Updated', 'sources' => ['spaces' => [$public->id]]])->assertRedirect($url);
        $private->addMember($viewer);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->has('posts', 1)->where('posts.0.id', $allowed->id));
    }

    public function test_feed_and_source_limits_are_enforced_on_the_server(): void
    {
        $viewer = User::factory()->create();
        $person = User::factory()->create();
        $this->actingAs($viewer)->postJson('/my-feeds', [
            'name' => str_repeat('n', 81), 'sources' => ['people' => [$person->id, $person->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'sources.people.0']);
        $this->postJson('/my-feeds', [
            'name' => 'Too many', 'sources' => ['people' => range(1, 16), 'spaces' => range(1, 15)],
        ])->assertUnprocessable()->assertJsonValidationErrors('sources');

        for ($i = 1; $i <= 20; $i++) {
            $this->makeFeed(['name' => 'Feed '.$i]);
        }
        $this->postJson('/my-feeds', ['name' => 'One too many', 'sources' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_export_includes_only_the_viewers_feed_and_portable_visible_references(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $person = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $space = Space::factory()->create();
        $topic = Topic::query()->create(['name' => 'studionotes']);
        Post::factory()->for($space)->create()->topics()->attach($topic);
        $this->actingAs($other)->makeFeed(['name' => 'Other member']);
        $this->actingAs($viewer)->makeFeed(['sources' => [
            'people' => [$person->id], 'spaces' => [$space->id], 'topics' => [$topic->id],
        ]]);
        $response = $this->withSession(['auth.password_confirmed_at' => time()])->get(route('personal-data.export'))->assertOk();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['My reading'], array_column($export['personal_feeds'], 'name'));
        $this->assertSame([
            'people' => [$person->handle], 'spaces' => [$space->slug], 'topics' => ['studionotes'],
        ], $export['personal_feeds'][0]['sources']);

        $person->update(['is_discoverable' => false]);
        $response = $this->get(route('personal-data.export'))->assertOk();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame([], $export['personal_feeds'][0]['sources']['people']);
        $person->delete();
        $this->assertDatabaseMissing('personal_feed_people', ['person_id' => $person->id]);
        $viewer->delete();
        $this->assertDatabaseMissing('personal_feeds', ['user_id' => $viewer->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeFeed(array $overrides): string
    {
        $response = $this->post('/my-feeds', $overrides + ['name' => 'My reading', 'sources' => []]);
        $response->assertRedirectContains('/my-feeds/');

        return (string) parse_url((string) $response->headers->get('Location'), PHP_URL_PATH);
    }
}
