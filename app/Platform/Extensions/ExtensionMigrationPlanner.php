<?php

namespace App\Platform\Extensions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ExtensionMigrationPlanner
{
    public function plan(ExtensionInspection $inspection): ExtensionMigrationPlan
    {
        $manifest = $inspection->manifest;

        if (! $manifest instanceof ExtensionManifest) {
            throw new ExtensionActivationException('Cannot plan migrations for an invalid extension manifest.');
        }

        if ($manifest->migrationPath === null) {
            return new ExtensionMigrationPlan(
                manifest: $manifest,
                migrationDirectory: null,
                files: [],
                records: [],
                status: ExtensionMigrationPlan::STATUS_NONE,
                message: 'This extension declares no database migrations.',
            );
        }

        if ($inspection->rootPath === null) {
            return $this->blocked($manifest, 'Extension source directory is unavailable.');
        }

        $directory = realpath($inspection->rootPath.DIRECTORY_SEPARATOR.$manifest->migrationPath);
        $rootPrefix = rtrim($inspection->rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($directory === false || ! is_dir($directory) || ! str_starts_with($directory.DIRECTORY_SEPARATOR, $rootPrefix)) {
            return $this->blocked($manifest, 'Declared migration directory is missing or resolves outside the extension.');
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [];
        sort($files);

        $maximumFiles = (int) config('extensions.migrations.max_files', 100);
        $maximumBytes = (int) config('extensions.migrations.max_file_bytes', 262144);

        if (count($files) > $maximumFiles) {
            return $this->blocked($manifest, "Migration directory exceeds the {$maximumFiles}-file limit.");
        }

        $migrationFiles = [];
        $problems = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $realFile = realpath($file);

            if (is_link($file) || $realFile === false || ! str_starts_with($realFile, $directory.DIRECTORY_SEPARATOR)) {
                $problems[] = "{$name}: migration files must be regular files inside the declared directory.";

                continue;
            }

            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+$/', $name) !== 1) {
                $problems[] = "{$name}: migration name must use Laravel's timestamped lowercase convention.";

                continue;
            }

            $size = filesize($realFile);

            if (! is_int($size) || $size > $maximumBytes) {
                $problems[] = "{$name}: migration file exceeds the configured size limit.";

                continue;
            }

            $checksum = hash_file('sha256', $realFile);

            if (! is_string($checksum)) {
                $problems[] = "{$name}: migration checksum could not be calculated.";

                continue;
            }

            $migrationFiles[] = new ExtensionMigrationFile($name, $realFile, $checksum);
        }

        if (! Schema::hasTable('platform_extension_migrations')) {
            $problems[] = 'Run the core migrations before planning extension schema changes.';

            return new ExtensionMigrationPlan(
                manifest: $manifest,
                migrationDirectory: $directory,
                files: $migrationFiles,
                records: [],
                status: ExtensionMigrationPlan::STATUS_BLOCKED,
                message: 'The extension migration ownership registry is unavailable.',
                problems: $problems,
            );
        }

        try {
            /** @var array<string, array{checksum: string, batch: int, applied_at: mixed}> $records */
            $records = DB::table('platform_extension_migrations')
                ->where('extension_id', $manifest->id)
                ->orderBy('migration')
                ->get(['migration', 'checksum', 'batch', 'applied_at'])
                ->mapWithKeys(static fn (object $record): array => [
                    (string) $record->migration => [
                        'checksum' => (string) $record->checksum,
                        'batch' => (int) $record->batch,
                        'applied_at' => $record->applied_at,
                    ],
                ])
                ->all();
        } catch (Throwable) {
            return $this->blocked($manifest, 'The extension migration ownership registry could not be read.');
        }

        $filesByName = [];

        foreach ($migrationFiles as $file) {
            $filesByName[$file->name] = $file;
            $record = $records[$file->name] ?? null;

            if (is_array($record) && ! hash_equals($record['checksum'], $file->checksum)) {
                $problems[] = "{$file->name}: applied migration source has changed.";
            }
        }

        foreach (array_keys($records) as $migration) {
            if (! isset($filesByName[$migration])) {
                $problems[] = "{$migration}: applied migration source is missing.";
            }
        }

        if ($problems !== []) {
            return new ExtensionMigrationPlan(
                manifest: $manifest,
                migrationDirectory: $directory,
                files: $migrationFiles,
                records: $records,
                status: ExtensionMigrationPlan::STATUS_BLOCKED,
                message: 'Migration integrity requires operator attention.',
                problems: $problems,
            );
        }

        $pending = count($migrationFiles) - count($records);

        return new ExtensionMigrationPlan(
            manifest: $manifest,
            migrationDirectory: $directory,
            files: $migrationFiles,
            records: $records,
            status: $pending > 0
                ? ExtensionMigrationPlan::STATUS_PENDING
                : ExtensionMigrationPlan::STATUS_APPLIED,
            message: $pending > 0
                ? "{$pending} reviewed migration".($pending === 1 ? ' is' : 's are').' waiting for deployment.'
                : ($migrationFiles === []
                    ? 'The declared migration directory is empty.'
                    : 'Every declared migration matches its applied checksum.'),
        );
    }

    /**
     * @param  list<ExtensionInspection>  $inspections
     * @return list<string>
     */
    public function retainedExtensionIds(array $inspections): array
    {
        if (! Schema::hasTable('platform_extension_migrations')) {
            return [];
        }

        $present = collect($inspections)
            ->map(fn (ExtensionInspection $inspection): ?string => $inspection->manifest?->id)
            ->filter()
            ->values()
            ->all();

        return array_values(DB::table('platform_extension_migrations')
            ->when($present !== [], fn ($query) => $query->whereNotIn('extension_id', $present))
            ->distinct()
            ->orderBy('extension_id')
            ->pluck('extension_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all());
    }

    private function blocked(ExtensionManifest $manifest, string $message): ExtensionMigrationPlan
    {
        return new ExtensionMigrationPlan(
            manifest: $manifest,
            migrationDirectory: null,
            files: [],
            records: [],
            status: ExtensionMigrationPlan::STATUS_BLOCKED,
            message: $message,
            problems: [$message],
        );
    }
}
