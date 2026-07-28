<?php

namespace App\Platform\Extensions;

use Carbon\CarbonInterface;

final readonly class ExtensionMigrationPlan
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_NONE = 'none';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  list<ExtensionMigrationFile>  $files
     * @param  array<string, array{checksum: string, batch: int, applied_at: CarbonInterface|string}>  $records
     * @param  list<string>  $problems
     */
    public function __construct(
        public ExtensionManifest $manifest,
        public ?string $migrationDirectory,
        public array $files,
        public array $records,
        public string $status,
        public string $message,
        public array $problems = [],
    ) {}

    public function isReadyForActivation(): bool
    {
        return in_array($this->status, [self::STATUS_APPLIED, self::STATUS_NONE], true);
    }

    /** @return list<ExtensionMigrationFile> */
    public function pendingFiles(): array
    {
        return array_values(array_filter(
            $this->files,
            fn (ExtensionMigrationFile $file): bool => ! isset($this->records[$file->name]),
        ));
    }

    /**
     * @return array{
     *     status: string,
     *     message: string,
     *     declared: int,
     *     applied: int,
     *     pending: int,
     *     blocked: int,
     *     uninstallData: string,
     *     items: list<array{name: string, status: string, batch: int|null, appliedAt: string|null}>
     * }
     */
    public function toArray(): array
    {
        $items = array_map(function (ExtensionMigrationFile $file): array {
            $record = $this->records[$file->name] ?? null;
            $drifted = is_array($record) && ! hash_equals($record['checksum'], $file->checksum);
            $appliedAt = is_array($record) ? $record['applied_at'] : null;

            return [
                'name' => $file->name,
                'status' => $drifted ? 'changed' : (is_array($record) ? 'applied' : 'pending'),
                'batch' => is_array($record) ? $record['batch'] : null,
                'appliedAt' => $appliedAt instanceof CarbonInterface
                    ? $appliedAt->toIso8601String()
                    : (is_string($appliedAt) ? $appliedAt : null),
            ];
        }, $this->files);

        return [
            'status' => $this->status,
            'message' => $this->message,
            'declared' => count($this->files),
            'applied' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['status'] === 'applied',
            )),
            'pending' => count(array_filter(
                $items,
                static fn (array $item): bool => $item['status'] === 'pending',
            )),
            'blocked' => count($this->problems),
            'uninstallData' => $this->manifest->uninstallDataPolicy,
            'items' => $items,
        ];
    }
}
