<?php

namespace App\Platform\Extensions;

final readonly class ExtensionMigrationFile
{
    public function __construct(
        public string $name,
        public string $path,
        public string $checksum,
    ) {}
}
