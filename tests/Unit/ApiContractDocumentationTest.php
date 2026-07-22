<?php

namespace Tests\Unit;

use JsonException;
use Tests\TestCase;

class ApiContractDocumentationTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_openapi_document_is_valid_json_with_the_expected_version_boundary(): void
    {
        $contract = $this->contract();

        $this->assertSame('3.1.0', $contract['openapi']);
        $this->assertSame('1.0.0-draft.1', $contract['info']['version']);
        $this->assertSame('/api/v1', $contract['servers'][0]['url']);
        $this->assertSame('http', $contract['components']['securitySchemes']['BearerToken']['type']);
        $this->assertSame('bearer', $contract['components']['securitySchemes']['BearerToken']['scheme']);
    }

    /**
     * @throws JsonException
     */
    public function test_contract_is_available_for_only_implemented_operations(): void
    {
        $contract = $this->contract();
        $expectedPaths = [
            '/feed',
            '/me',
            '/notifications',
            '/notifications/read-all',
            '/notifications/{notification}/read',
            '/posts/{post}',
            '/posts/{post}/comments',
            '/posts/{post}/media',
            '/profiles/{handle}',
            '/spaces',
            '/spaces/{slug}',
        ];
        $paths = array_keys($contract['paths']);
        $availableGetPaths = [
            '/feed',
            '/me',
            '/notifications',
            '/posts/{post}',
            '/posts/{post}/comments',
            '/posts/{post}/media',
            '/profiles/{handle}',
            '/spaces',
            '/spaces/{slug}',
        ];
        $availablePatchPaths = [
            '/notifications/{notification}/read',
            '/notifications/read-all',
        ];

        sort($paths);

        $this->assertSame($expectedPaths, $paths);

        foreach ($contract['paths'] as $path => $pathItem) {
            $methods = array_keys($pathItem);
            $availableMethods = in_array($path, $availableGetPaths, true)
                ? ['get']
                : ['patch'];

            $this->assertSame(
                $availableMethods,
                $methods,
                $path.' methods must match the implemented slice.',
            );

            foreach ($methods as $method) {
                $operation = $pathItem[$method];
                $isAvailable = in_array($path, [...$availableGetPaths, ...$availablePatchPaths], true);
                $this->assertSame(
                    $isAvailable ? 'available' : 'planned',
                    $operation['x-lineweb-status'],
                );
                $this->assertArrayNotHasKey('requestBody', $operation);
            }
        }

        $this->assertArrayHasKey('422', $contract['paths']['/feed']['get']['responses']);
    }

    /**
     * @throws JsonException
     */
    public function test_every_operation_declares_ability_and_standard_security_failures(): void
    {
        $contract = $this->contract();
        $allowedAbilities = [
            'profile:read',
            'profiles:read',
            'spaces:read',
            'feed:read',
            'notifications:read',
            'notifications:write',
        ];

        foreach ($contract['paths'] as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                $this->assertContains($operation['x-required-ability'], $allowedAbilities, $path.' '.$method);
                $this->assertArrayHasKey('401', $operation['responses'], $path.' '.$method);
                $this->assertArrayHasKey('403', $operation['responses'], $path.' '.$method);
                $this->assertArrayHasKey('429', $operation['responses'], $path.' '.$method);
            }
        }

        $throttleHeaders = $contract['components']['responses']['TooManyRequests']['headers'];

        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
            $this->assertArrayHasKey($header, $throttleHeaders);
        }
    }

    /**
     * @throws JsonException
     */
    public function test_public_schemas_do_not_expose_sensitive_storage_or_account_fields(): void
    {
        $contract = $this->contract();
        $schemas = $contract['components']['schemas'];

        $this->assertSame(
            ['handle', 'name', 'headline', 'bio', 'location', 'website_url', 'member_since', 'viewer'],
            array_keys($schemas['Profile']['properties']),
        );
        $this->assertSame(
            ['url', 'alt', 'width', 'height', 'mime_type'],
            array_keys($schemas['Media']['properties']),
        );
        $this->assertSame(
            ['id', 'kind', 'title', 'description', 'created_at', 'read_at', 'available', 'target'],
            array_keys($schemas['Notification']['properties']),
        );
        $this->assertSame(
            ['slug', 'name', 'description', 'visibility', 'member_count', 'viewer'],
            array_keys($schemas['Space']['properties']),
        );
        $this->assertSame(
            ['id', 'body', 'published_at', 'media', 'comments_count', 'author', 'space', 'viewer'],
            array_keys($schemas['Post']['properties']),
        );
        $this->assertSame(
            ['handle', 'name', 'headline', 'profile_visible'],
            array_keys($schemas['ProfileSummary']['properties']),
        );

        foreach (['email', 'password', 'token', 'disk', 'path', 'checksum', 'data'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $schemas['Profile']['properties']);
            $this->assertArrayNotHasKey($forbidden, $schemas['Space']['properties']);
            $this->assertArrayNotHasKey($forbidden, $schemas['Media']['properties']);
            $this->assertArrayNotHasKey($forbidden, $schemas['Post']['properties']);
            $this->assertArrayNotHasKey($forbidden, $schemas['ProfileSummary']['properties']);
            $this->assertArrayNotHasKey($forbidden, $schemas['Notification']['properties']);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function contract(): array
    {
        $contents = file_get_contents(base_path('docs/openapi.json'));

        $this->assertIsString($contents);

        /** @var array<string, mixed> $contract */
        $contract = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $contract;
    }
}
