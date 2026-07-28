<?php

namespace App\Platform\Extensions;

use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;

final class ExtensionMigrationRepository implements MigrationRepositoryInterface
{
    private ?string $connection = null;

    /**
     * @param  array<string, string>  $checksums
     */
    public function __construct(
        private readonly DatabaseManager $resolver,
        private readonly string $extensionId,
        private readonly string $extensionVersion,
        private readonly array $checksums,
        private readonly string $table = 'platform_extension_migrations',
    ) {}

    /** @return list<string> */
    public function getRan(): array
    {
        return array_values($this->query()
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('migration')
            ->map(static fn (mixed $migration): string => (string) $migration)
            ->all());
    }

    /** @return list<object> */
    public function getMigrations($steps): array
    {
        return array_values($this->query()
            ->where('batch', '>=', 1)
            ->orderByDesc('batch')
            ->orderByDesc('migration')
            ->limit($steps)
            ->get(['id', 'migration', 'batch'])
            ->all());
    }

    /** @return list<object> */
    public function getMigrationsByBatch($batch): array
    {
        return array_values($this->query()
            ->where('batch', $batch)
            ->orderByDesc('migration')
            ->get(['id', 'migration', 'batch'])
            ->all());
    }

    /** @return list<object> */
    public function getLast(): array
    {
        return array_values($this->query()
            ->where('batch', $this->getLastBatchNumber())
            ->orderByDesc('migration')
            ->get(['id', 'migration', 'batch'])
            ->all());
    }

    /** @return array<string, int> */
    public function getMigrationBatches(): array
    {
        return $this->query()
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('batch', 'migration')
            ->map(static fn (mixed $batch): int => (int) $batch)
            ->all();
    }

    public function log($file, $batch): void
    {
        $checksum = $this->checksums[$file] ?? null;

        if (! is_string($checksum)) {
            throw new RuntimeException("No reviewed checksum is available for extension migration {$file}.");
        }

        $this->connection()->table($this->table)->insert([
            'extension_id' => $this->extensionId,
            'migration' => $file,
            'extension_version' => $this->extensionVersion,
            'checksum' => $checksum,
            'batch' => $batch,
            'applied_at' => now(),
        ]);
    }

    public function delete($migration): void
    {
        $this->query()
            ->where('migration', $migration->migration)
            ->delete();
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    public function createRepository(): void
    {
        $this->connection()->getSchemaBuilder()->create($this->table, function (Blueprint $table): void {
            $table->id();
            $table->string('extension_id', 80);
            $table->string('migration', 180);
            $table->string('extension_version', 40);
            $table->char('checksum', 64);
            $table->unsignedInteger('batch');
            $table->timestamp('applied_at')->useCurrent();

            $table->unique(['extension_id', 'migration']);
            $table->index(['extension_id', 'batch']);
        });
    }

    public function repositoryExists(): bool
    {
        return $this->connection()->getSchemaBuilder()->hasTable($this->table);
    }

    public function deleteRepository(): void
    {
        $this->connection()->getSchemaBuilder()->dropIfExists($this->table);
    }

    public function setSource($name): void
    {
        $this->connection = $name;
    }

    private function getLastBatchNumber(): int
    {
        return (int) ($this->query()->max('batch') ?? 0);
    }

    private function query(): Builder
    {
        return $this->connection()
            ->table($this->table)
            ->where('extension_id', $this->extensionId)
            ->useWritePdo();
    }

    private function connection(): Connection
    {
        return $this->resolver->connection($this->connection);
    }
}
