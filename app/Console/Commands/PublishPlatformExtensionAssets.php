<?php

namespace App\Console\Commands;

use App\Platform\Extensions\ExtensionAssetException;
use App\Platform\Extensions\ExtensionAssetPlan;
use App\Platform\Extensions\ExtensionAssetPublisher;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PublishPlatformExtensionAssets extends Command
{
    use ConfirmableTrait;

    protected $signature = 'platform:extensions:publish-assets
        {extension* : Reviewed extension ids whose browser assets should be published}
        {--force : Force the operation to run in production}';

    protected $description = 'Publish immutable browser assets for reviewed local extensions';

    public function handle(ExtensionAssetPublisher $publisher): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $extensions = $this->argument('extension');
        $ids = is_array($extensions) ? array_values(array_filter($extensions, is_string(...))) : [];
        if (! is_array($extensions) || count($ids) !== count($extensions)) {
            $this->components->error('Extension IDs must be text.');

            return self::FAILURE;
        }

        $lock = Cache::lock('platform-extension-assets', 600);

        if (! $lock->get()) {
            $this->components->error('Another extension asset publication is already running.');

            return self::FAILURE;
        }

        try {
            $plans = $publisher->publish($ids);

            foreach ($plans as $id => $plan) {
                if ($plan->status === ExtensionAssetPlan::STATUS_NONE) {
                    $this->components->info("{$id}: no browser assets declared.");
                } else {
                    $this->components->info(
                        "{$id}: verified ".count($plan->publishedAssets)." immutable browser asset(s) in release {$plan->release}.",
                    );
                }
            }

            return self::SUCCESS;
        } catch (ExtensionAssetException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
