<?php

namespace App\Platform\Extensions;

final class ExtensionAssetManager
{
    /**
     * @var array{
     *     version: string,
     *     styles: list<array{extension: string, url: string, integrity: string}>,
     *     scripts: list<array{extension: string, url: string, integrity: string}>
     * }|null
     */
    private ?array $payload = null;

    public function __construct(
        private readonly ExtensionActivator $activator,
        private readonly ExtensionInspector $inspector,
        private readonly ExtensionAssetPlanner $planner,
    ) {}

    /**
     * @return array{
     *     version: string,
     *     styles: list<array{extension: string, url: string, integrity: string}>,
     *     scripts: list<array{extension: string, url: string, integrity: string}>
     * }
     */
    public function payload(): array
    {
        if ($this->payload !== null) {
            return $this->payload;
        }

        $enabled = $this->activator->enabledIds();
        $inspections = $this->inspector->inspect();
        $byId = [];

        foreach ($inspections as $inspection) {
            if ($inspection->manifest instanceof ExtensionManifest) {
                $byId[$inspection->manifest->id] = $inspection;
            }
        }

        $styles = [];
        $scripts = [];
        $releases = [];

        foreach ($enabled as $id) {
            $inspection = $byId[$id] ?? null;

            if (! $inspection instanceof ExtensionInspection) {
                throw new ExtensionActivationException("Enabled extension '{$id}' was not found while loading browser assets.");
            }

            $plan = $this->planner->plan($inspection);

            if (! $plan->isReadyForActivation()) {
                throw new ExtensionActivationException(
                    "Enabled extension '{$id}' browser assets cannot load: {$plan->message}",
                );
            }

            if ($plan->status === ExtensionAssetPlan::STATUS_NONE) {
                continue;
            }

            $releases[] = "{$id}:{$plan->release}";

            foreach ($plan->publishedAssets as $asset) {
                $entry = [
                    'extension' => $id,
                    'url' => asset('extensions/'.$asset['file']),
                    'integrity' => $this->integrity($asset['checksum']),
                ];

                if ($asset['type'] === ExtensionAssetFile::TYPE_STYLE) {
                    $styles[] = $entry;
                } else {
                    $scripts[] = $entry;
                }
            }
        }

        return $this->payload = [
            'version' => hash('sha256', implode('|', $releases)),
            'styles' => $styles,
            'scripts' => $scripts,
        ];
    }

    private function integrity(string $checksum): string
    {
        $binary = hex2bin($checksum);

        if ($binary === false) {
            throw new ExtensionActivationException('A published extension asset checksum is invalid.');
        }

        return 'sha256-'.base64_encode($binary);
    }
}
