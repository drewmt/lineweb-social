<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExtensionCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('extensions.paths', [base_path('extensions')]);
        config()->set('extensions.enabled', []);
    }

    public function test_regular_members_cannot_open_the_extension_center(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.extensions.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_review_local_extension_compatibility(): void
    {
        $administrator = User::factory()->create([
            'platform_role' => 'administrator',
        ]);

        $this->actingAs($administrator)
            ->get(route('admin.extensions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/extensions')
                ->where('coreVersion', '0.2.0-beta.1')
                ->where('summary.discovered', 1)
                ->where('summary.active', 0)
                ->where('summary.compatible', 1)
                ->where('summary.actionRequired', 0)
                ->where('summary.migrationPending', 0)
                ->where('summary.migrationBlocked', 0)
                ->where('summary.retainedData', 0)
                ->where('summary.assetsPublished', 0)
                ->where('summary.assetsAttention', 1)
                ->where('retainedExtensionIds', [])
                ->has('extensions', 1)
                ->where('extensions.0.id', 'example-polls')
                ->where('extensions.0.status', 'compatible')
                ->where('extensions.0.active', false)
                ->where('extensions.0.database.migrations', null)
                ->where('extensions.0.database.uninstallData', 'retain')
                ->where('extensions.0.migrations.status', 'none')
                ->where('extensions.0.assets.styles', [
                    'dist/example-polls.css',
                ])
                ->where('extensions.0.assets.scripts', [
                    'dist/example-polls.js',
                ])
                ->where('extensions.0.assetPlan.status', 'unpublished')
                ->where('extensions.0.assetPlan.declared', 2)
                ->where('extensions.0.permissions', [
                    'posts.read',
                    'posts.write',
                    'spaces.read',
                ])
                ->where('extensions.0.uiSlots', [
                    'feed.composer.after',
                    'post.actions',
                ]));
    }

    public function test_extension_audit_command_supports_human_and_machine_readable_output(): void
    {
        $this->assertSame(0, Artisan::call('platform:extensions'));
        $output = Artisan::output();

        $this->assertStringContainsString(
            'Lineweb Social 0.2.0-beta.1',
            $output,
        );
        $this->assertStringContainsString('Example Polls', $output);
        $this->assertStringContainsString('compatible', $output);

        $this->assertSame(0, Artisan::call('platform:extensions', ['--json' => true]));

        $payload = json_decode(Artisan::output(), true, 32, JSON_THROW_ON_ERROR);

        $this->assertTrue($payload['ready']);
        $this->assertSame([], $payload['enabled']);
        $this->assertSame([], $payload['retainedData']);
        $this->assertSame('example-polls', $payload['extensions'][0]['id']);
        $this->assertFalse($payload['extensions'][0]['active']);
        $this->assertSame('none', $payload['extensions'][0]['migrations']['status']);
        $this->assertSame('unpublished', $payload['extensions'][0]['assetPlan']['status']);
    }

    public function test_extension_audit_command_fails_for_unsafe_deployment_state(): void
    {
        config()->set('extensions.paths', [
            base_path('tests/Fixtures/extension-center'),
        ]);

        $this->assertSame(1, Artisan::call('platform:extensions'));
        $output = Artisan::output();

        $this->assertStringContainsString('invalid', $output);
        $this->assertStringContainsString('incompatible', $output);
    }
}
