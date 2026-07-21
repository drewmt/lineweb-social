<?php

namespace Tests\Feature\Settings;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_lists_token_metadata_without_plaintext_or_digest(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken(
            'Reporting tool',
            ['profile:read'],
            now()->addDays(30),
        );

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/security')
                ->has('apiTokens', 1)
                ->where('apiTokens.0.name', 'Reporting tool')
                ->where('apiTokens.0.abilities', ['profile:read'])
                ->missing('apiTokens.0.token')
                ->missing('apiTokens.0.plainTextToken'),
            )
            ->assertDontSee($newToken->plainTextToken)
            ->assertDontSee($newToken->accessToken->token);
    }

    public function test_token_creation_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('api-tokens.store'), ['name' => 'My phone'])
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_confirmed_member_can_create_one_expiring_limited_token(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('security.edit'))
            ->post(route('api-tokens.store'), [
                'name' => '  My phone  ',
                'abilities' => ['profile:read', 'profiles:read', 'spaces:read'],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('security.edit'));

        $token = $user->tokens()->sole();
        $flash = $response->getSession()->get(SessionKey::FLASH_DATA);

        $this->assertSame('My phone', $token->name);
        $this->assertSame(['profile:read', 'profiles:read', 'spaces:read'], $token->abilities);
        $this->assertSame(now()->addDays(30)->timestamp, $token->expires_at?->timestamp);
        $this->assertIsArray($flash);
        $this->assertSame('My phone', $flash['apiToken']['name']);
        $this->assertTrue(Str::startsWith($flash['apiToken']['plainTextToken'], $token->getKey().'|ls_'));
        $this->assertSame(
            hash('sha256', Str::after($flash['apiToken']['plainTextToken'], '|')),
            $token->token,
        );
    }

    public function test_token_names_are_validated_and_active_tokens_are_capped(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), [
                'name' => ' ',
                'abilities' => ['profile:read'],
            ])
            ->assertSessionHasErrors('name');

        for ($token = 1; $token <= 10; $token++) {
            $user->createToken(
                'Client '.$token,
                ['profile:read'],
                now()->addDays(30),
            );
        }

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), [
                'name' => 'One too many',
                'abilities' => ['profile:read'],
            ])
            ->assertSessionHasErrors('name');

        $this->assertCount(10, $user->tokens()->get());
    }

    public function test_token_abilities_are_required_and_limited_to_the_public_allowlist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), [
                'name' => 'Unsafe client',
                'abilities' => ['*'],
            ])
            ->assertSessionHasErrors('abilities.0');

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('api-tokens.store'), [
                'name' => 'Missing abilities',
            ])
            ->assertSessionHasErrors('abilities');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_member_can_revoke_one_or_all_of_their_tokens_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $first = $user->createToken('First', ['profile:read'], now()->addDays(30))->accessToken;
        $second = $user->createToken('Second', ['profile:read'], now()->addDays(30))->accessToken;
        $otherToken = $other->createToken('Other', ['profile:read'], now()->addDays(30))->accessToken;

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('api-tokens.destroy', $otherToken->getKey()))
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('api-tokens.destroy', $first->getKey()))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $first->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $second->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->getKey()]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('api-tokens.destroy-all'))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $second->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->getKey()]);
    }

    public function test_password_change_reset_and_account_deletion_revoke_tokens(): void
    {
        $changed = User::factory()->create();
        $changed->createToken('Changed', ['profile:read'], now()->addDays(30));

        $this->actingAs($changed)
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();
        $this->assertCount(0, $changed->tokens()->get());

        $reset = User::factory()->create();
        $reset->createToken('Reset', ['profile:read'], now()->addDays(30));
        (new ResetUserPassword)->reset($reset, [
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ]);
        $this->assertCount(0, $reset->tokens()->get());

        $deleted = User::factory()->create();
        $deletedTokenId = $deleted->createToken(
            'Deleted',
            ['profile:read'],
            now()->addDays(30),
        )->accessToken->getKey();
        $deleted->delete();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $deletedTokenId]);
    }
}
