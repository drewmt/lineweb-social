<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurrentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_read_token_returns_only_the_safe_current_profile_contract(): void
    {
        $user = User::factory()->create([
            'name' => 'Andrew Matia',
            'headline' => 'Open-source builder',
            'bio' => 'Building useful community software.',
            'location' => 'Thessaloniki',
            'website_url' => 'https://www.lineweb.gr',
        ]);
        $plainTextToken = $user->createToken(
            'Test client',
            ['profile:read'],
            now()->addDays(30),
        )->plainTextToken;

        $response = $this->withToken($plainTextToken)->getJson(route('api.v1.me'));

        $response
            ->assertOk()
            ->assertJsonPath('data.handle', $user->handle)
            ->assertJsonPath('data.name', 'Andrew Matia')
            ->assertJsonPath('data.headline', 'Open-source builder')
            ->assertJsonPath('data.bio', 'Building useful community software.')
            ->assertJsonPath('data.location', 'Thessaloniki')
            ->assertJsonPath('data.website_url', 'https://www.lineweb.gr')
            ->assertJsonPath('data.member_since', $user->created_at?->toDateString())
            ->assertJsonPath('data.viewer.is_self', true)
            ->assertJsonPath('data.viewer.is_muted', false)
            ->assertJsonPath('data.viewer.is_following', false)
            ->assertJsonPath('data.viewer.can_follow', false)
            ->assertJsonPath('data.stats.followers', 0)
            ->assertJsonPath('data.stats.following', 0)
            ->assertHeader('X-RateLimit-Limit', '120');

        $this->assertSame(
            ['handle', 'name', 'headline', 'bio', 'location', 'website_url', 'member_since', 'stats', 'viewer'],
            array_keys($response->json('data')),
        );
        $this->assertRequestId($response->headers->get('X-Request-ID'));
        $this->assertNotNull($user->tokens()->firstOrFail()->fresh()->last_used_at);
    }

    public function test_missing_token_uses_the_stable_error_envelope(): void
    {
        $response = $this->getJson(route('api.v1.me'));

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Authentication is required.')
            ->assertJsonPath('code', 'unauthenticated');

        $requestId = $response->json('request_id');

        $this->assertRequestId($requestId);
        $response->assertHeader('X-Request-ID', $requestId);
    }

    public function test_a_web_session_without_a_bearer_token_cannot_use_the_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_request_ids_are_server_generated_and_allowed_cors_origins_are_explicit(): void
    {
        config(['cors.allowed_origins' => ['https://client.example.test']]);

        $user = User::factory()->create();
        $plainTextToken = $user->createToken(
            'Browser client',
            ['profile:read'],
            now()->addDays(30),
        )->plainTextToken;

        $response = $this
            ->withToken($plainTextToken)
            ->withHeaders([
                'Origin' => 'https://client.example.test',
                'X-Request-ID' => 'client-controlled-value',
            ])
            ->getJson(route('api.v1.me'));

        $response
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://client.example.test')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->assertRequestId($response->headers->get('X-Request-ID'));
        $this->assertNotSame('client-controlled-value', $response->headers->get('X-Request-ID'));

        $this
            ->withToken($plainTextToken)
            ->withHeader('Origin', 'https://untrusted.example.test')
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://client.example.test');
    }

    public function test_token_requires_the_declared_profile_read_ability(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken(
            'Wrong scope',
            ['spaces:read'],
            now()->addDays(30),
        )->plainTextToken;

        $this->withToken($plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonMissing(['abilities' => ['profile:read']]);
    }

    public function test_unverified_accounts_cannot_use_the_api(): void
    {
        $user = User::factory()->unverified()->create();
        $plainTextToken = $user->createToken(
            'Unverified account',
            ['profile:read'],
            now()->addDays(30),
        )->plainTextToken;

        $this->withToken($plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_expired_and_revoked_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $expired = $user->createToken(
            'Expired',
            ['profile:read'],
            now()->subMinute(),
        );
        $revoked = $user->createToken(
            'Revoked',
            ['profile:read'],
            now()->addDays(30),
        );
        $revokedPlainText = $revoked->plainTextToken;
        $revoked->accessToken->delete();

        $this->withToken($expired->plainTextToken)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->withToken($revokedPlainText)
            ->getJson(route('api.v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_read_limiter_returns_retry_metadata_and_the_stable_error(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken(
            'Rate limit test',
            ['profile:read'],
            now()->addDays(30),
        )->plainTextToken;

        for ($attempt = 1; $attempt <= 120; $attempt++) {
            $this->withToken($plainTextToken)
                ->getJson(route('api.v1.me'))
                ->assertOk();
        }

        $response = $this->withToken($plainTextToken)->getJson(route('api.v1.me'));

        $response
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'rate_limited')
            ->assertHeader('X-RateLimit-Limit', '120')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('Retry-After');
        $this->assertRequestId($response->headers->get('X-Request-ID'));
    }

    private function assertRequestId(mixed $requestId): void
    {
        $this->assertIsString($requestId);
        $this->assertTrue(Str::isUuid($requestId));
    }
}
