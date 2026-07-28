<?php

namespace Tests\Feature;

use App\Platform\Extensions\ExtensionActivationException;
use App\Platform\Extensions\ExtensionActivator;
use App\Platform\Extensions\ExtensionInspector;
use App\Platform\Extensions\ExtensionMigrationPlan;
use App\Platform\Extensions\ExtensionMigrationPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExtensionMigrationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('extensions.core_version', '0.1.0-alpha.1');
        config()->set('extensions.paths', [
            base_path('tests/Fixtures/extension-migrations'),
        ]);
        config()->set('extensions.enabled', []);
    }

    public function test_planner_reports_reviewed_pending_migrations_without_loading_them(): void
    {
        $inspection = (new ExtensionInspector)->inspect()[0];
        $plan = (new ExtensionMigrationPlanner)->plan($inspection);

        $this->assertSame(ExtensionMigrationPlan::STATUS_PENDING, $plan->status);
        $this->assertCount(2, $plan->pendingFiles());
        $this->assertSame('retain', $plan->manifest->uninstallDataPolicy);
        $this->assertFalse(Schema::hasTable('extension_poll_options'));
    }

    public function test_migrate_command_requires_backup_and_applies_each_migration_once(): void
    {
        $this->assertSame(1, Artisan::call('platform:extensions:migrate', [
            'extension' => ['migration-polls'],
        ]));
        $this->assertStringContainsString('--backup-confirmed', Artisan::output());

        $this->assertSame(0, $this->migrate());
        $this->assertTrue(Schema::hasTable('extension_poll_options'));
        $this->assertTrue(Schema::hasTable('extension_poll_votes'));
        $this->assertDatabaseCount('platform_extension_migrations', 2);
        $this->assertDatabaseHas('platform_extension_migrations', [
            'extension_id' => 'migration-polls',
            'extension_version' => '1.2.0',
            'batch' => 1,
        ]);

        $this->assertSame(0, $this->migrate());
        $this->assertDatabaseCount('platform_extension_migrations', 2);

        $plan = (new ExtensionMigrationPlanner)->plan((new ExtensionInspector)->inspect()[0]);
        $this->assertSame(ExtensionMigrationPlan::STATUS_APPLIED, $plan->status);
        $this->assertTrue($plan->isReadyForActivation());
    }

    public function test_every_selected_extension_is_preflighted_before_any_migration_runs(): void
    {
        $this->assertSame(1, Artisan::call('platform:extensions:migrate', [
            'extension' => ['migration-polls', 'missing-extension'],
            '--backup-confirmed' => true,
        ]));

        $this->assertStringContainsString('was not found', Artisan::output());
        $this->assertFalse(Schema::hasTable('extension_poll_options'));
        $this->assertDatabaseCount('platform_extension_migrations', 0);
    }

    public function test_changed_applied_source_blocks_rollback_and_activation(): void
    {
        $this->assertSame(0, $this->migrate());

        DB::table('platform_extension_migrations')
            ->where('extension_id', 'migration-polls')
            ->where('migration', '2026_07_28_000000_create_extension_poll_options')
            ->update(['checksum' => str_repeat('0', 64)]);

        $plan = (new ExtensionMigrationPlanner)->plan((new ExtensionInspector)->inspect()[0]);
        $this->assertSame(ExtensionMigrationPlan::STATUS_BLOCKED, $plan->status);
        $this->assertStringContainsString('integrity', $plan->message);

        $this->assertSame(1, Artisan::call('platform:extensions:rollback', [
            'extension' => 'migration-polls',
            '--backup-confirmed' => true,
        ]));
        $this->assertStringContainsString('integrity', Artisan::output());
        $this->assertTrue(Schema::hasTable('extension_poll_options'));

        config()->set('extensions.enabled', ['migration-polls']);

        $this->expectException(ExtensionActivationException::class);
        $this->expectExceptionMessage('cannot start');

        $this->activator()->activateConfigured();
    }

    public function test_latest_extension_batch_can_be_rolled_back_only_while_disabled(): void
    {
        $this->assertSame(0, $this->migrate());

        config()->set('extensions.enabled', ['migration-polls']);

        $this->assertSame(1, Artisan::call('platform:extensions:rollback', [
            'extension' => 'migration-polls',
            '--backup-confirmed' => true,
        ]));
        $this->assertStringContainsString('Disable extension', Artisan::output());
        $this->assertTrue(Schema::hasTable('extension_poll_options'));

        config()->set('extensions.enabled', []);

        $this->assertSame(0, Artisan::call('platform:extensions:rollback', [
            'extension' => 'migration-polls',
            '--backup-confirmed' => true,
        ]));
        $this->assertFalse(Schema::hasTable('extension_poll_options'));
        $this->assertFalse(Schema::hasTable('extension_poll_votes'));
        $this->assertDatabaseCount('platform_extension_migrations', 0);
    }

    public function test_removed_extension_source_keeps_an_explicit_retained_data_record(): void
    {
        $this->assertSame(0, $this->migrate());

        config()->set('extensions.paths', [
            base_path('tests/Fixtures/missing-extensions'),
        ]);

        $inspections = (new ExtensionInspector)->inspect();

        $this->assertSame(
            ['migration-polls'],
            (new ExtensionMigrationPlanner)->retainedExtensionIds($inspections),
        );
        $this->assertDatabaseCount('platform_extension_migrations', 2);
    }

    private function migrate(): int
    {
        return Artisan::call('platform:extensions:migrate', [
            'extension' => ['migration-polls'],
            '--backup-confirmed' => true,
        ]);
    }

    private function activator(): ExtensionActivator
    {
        return new ExtensionActivator(
            $this->app,
            new ExtensionInspector,
            new ExtensionMigrationPlanner,
        );
    }
}
