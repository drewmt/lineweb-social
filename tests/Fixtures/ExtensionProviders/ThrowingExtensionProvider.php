<?php

namespace Tests\Fixtures\ExtensionProviders;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

class ThrowingExtensionProvider extends ServiceProvider
{
    public function register(): void
    {
        throw new RuntimeException('Synthetic provider registration failure.');
    }
}
