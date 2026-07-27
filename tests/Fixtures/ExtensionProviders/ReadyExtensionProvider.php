<?php

namespace Tests\Fixtures\ExtensionProviders;

use Illuminate\Support\ServiceProvider;

class ReadyExtensionProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('extensions.ready', true);
    }
}
