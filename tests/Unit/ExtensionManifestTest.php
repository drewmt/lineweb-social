<?php

namespace Tests\Unit;

use App\Platform\Extensions\ExtensionManifest;
use App\Platform\Extensions\ExtensionRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExtensionManifestTest extends TestCase
{
    /** @var list<string> */
    private array $permissions = ['posts.read', 'posts.write', 'spaces.read'];

    /** @var list<string> */
    private array $slots = ['feed.composer.after', 'post.actions'];

    public function test_valid_manifest_is_loaded(): void
    {
        $manifest = ExtensionManifest::fromArray(
            $this->validManifest(),
            $this->permissions,
            $this->slots,
        );

        $this->assertSame('example-polls', $manifest->id);
        $this->assertSame('0.1.0', $manifest->version);
        $this->assertSame(['posts.read'], $manifest->permissions);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidManifestProvider')]
    public function test_invalid_manifest_values_are_rejected(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExtensionManifest::fromArray(
            array_replace($this->validManifest(), $changes),
            $this->permissions,
            $this->slots,
        );
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidManifestProvider(): iterable
    {
        yield 'unsafe id' => [['id' => '../polls']];
        yield 'invalid version' => [['version' => 'first-release']];
        yield 'unknown permission' => [['permissions' => ['database.admin']]];
        yield 'unknown slot' => [['ui_slots' => ['feed.anywhere']]];
        yield 'unqualified provider' => [['provider' => 'PollsProvider']];
    }

    public function test_registry_discovers_the_reference_extension(): void
    {
        $extensions = (new ExtensionRegistry)->discover([base_path('extensions')]);

        $this->assertArrayHasKey('example-polls', $extensions);
        $this->assertSame('Example Polls', $extensions['example-polls']->name);
    }

    public function test_registry_rejects_duplicate_ids(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate extension id');

        (new ExtensionRegistry)->discover([base_path('tests/Fixtures/extensions')]);
    }

    /** @return array<string, mixed> */
    private function validManifest(): array
    {
        return [
            'id' => 'example-polls',
            'name' => 'Example Polls',
            'version' => '0.1.0',
            'license' => 'MIT',
            'authors' => [['name' => 'Lineweb', 'url' => 'https://www.lineweb.gr']],
            'core' => '^0.1',
            'provider' => 'Extensions\\ExamplePolls\\PollsServiceProvider',
            'permissions' => ['posts.read'],
            'ui_slots' => ['feed.composer.after'],
        ];
    }
}
