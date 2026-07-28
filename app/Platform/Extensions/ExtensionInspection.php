<?php

namespace App\Platform\Extensions;

final readonly class ExtensionInspection
{
    public const STATUS_COMPATIBLE = 'compatible';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_INCOMPATIBLE = 'incompatible';

    public const STATUS_INVALID = 'invalid';

    public function __construct(
        public string $directory,
        public ?string $rootPath,
        public ?ExtensionManifest $manifest,
        public string $status,
        public string $message,
    ) {}

    public function isCompatible(): bool
    {
        return $this->status === self::STATUS_COMPATIBLE;
    }

    /**
     * @return array{
     *     key: string,
     *     id: string|null,
     *     name: string,
     *     version: string|null,
     *     core: string|null,
     *     license: string|null,
     *     authors: list<array{name: string, url?: string}>,
     *     permissions: list<string>,
     *     uiSlots: list<string>,
     *     provider: string|null,
     *     database: array{migrations: string|null, uninstallData: string},
     *     status: string,
     *     message: string
     * }
     */
    public function toArray(): array
    {
        $manifest = $this->manifest;

        return [
            'key' => $this->directory,
            'id' => $manifest instanceof ExtensionManifest ? $manifest->id : null,
            'name' => $manifest instanceof ExtensionManifest ? $manifest->name : $this->directory,
            'version' => $manifest instanceof ExtensionManifest ? $manifest->version : null,
            'core' => $manifest instanceof ExtensionManifest ? $manifest->core : null,
            'license' => $manifest instanceof ExtensionManifest ? $manifest->license : null,
            'authors' => $manifest instanceof ExtensionManifest ? $manifest->authors : [],
            'permissions' => $manifest instanceof ExtensionManifest ? $manifest->permissions : [],
            'uiSlots' => $manifest instanceof ExtensionManifest ? $manifest->uiSlots : [],
            'provider' => $manifest instanceof ExtensionManifest ? $manifest->provider : null,
            'database' => [
                'migrations' => $manifest instanceof ExtensionManifest ? $manifest->migrationPath : null,
                'uninstallData' => $manifest instanceof ExtensionManifest ? $manifest->uninstallDataPolicy : 'retain',
            ],
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
