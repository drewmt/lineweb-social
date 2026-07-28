<?php

namespace App\Platform\Extensions;

use Composer\Semver\VersionParser;
use InvalidArgumentException;
use JsonException;
use UnexpectedValueException;

final readonly class ExtensionManifest
{
    /**
     * @param  list<array{name: string, url?: string}>  $authors
     * @param  list<string>  $permissions
     * @param  list<string>  $uiSlots
     */
    private function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $license,
        public array $authors,
        public string $core,
        public string $provider,
        public array $permissions,
        public array $uiSlots,
        public ?string $migrationPath,
        public string $uninstallDataPolicy,
    ) {}

    /**
     * @param  list<string>  $allowedPermissions
     * @param  list<string>  $allowedUiSlots
     */
    public static function fromFile(
        string $path,
        array $allowedPermissions,
        array $allowedUiSlots,
    ): self {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidArgumentException("Unable to read extension manifest: {$path}");
        }

        try {
            $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                "Invalid JSON in extension manifest: {$path}",
                previous: $exception,
            );
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException('An extension manifest must contain a JSON object.');
        }

        return self::fromArray($data, $allowedPermissions, $allowedUiSlots);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $allowedPermissions
     * @param  list<string>  $allowedUiSlots
     */
    public static function fromArray(
        array $data,
        array $allowedPermissions,
        array $allowedUiSlots,
    ): self {
        $id = self::requiredString($data, 'id', 80);
        $name = self::requiredString($data, 'name', 120);
        $version = self::requiredString($data, 'version', 40);
        $license = self::requiredString($data, 'license', 80);
        $core = self::requiredString($data, 'core', 40);
        $provider = self::requiredString($data, 'provider', 255);

        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $id) !== 1) {
            throw new InvalidArgumentException('Extension id must be a lowercase, kebab-case identifier.');
        }

        if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new InvalidArgumentException('Extension version must be a semantic version.');
        }

        try {
            (new VersionParser)->parseConstraints($core);
        } catch (UnexpectedValueException $exception) {
            throw new InvalidArgumentException(
                'Core compatibility constraint must use Composer version syntax.',
                previous: $exception,
            );
        }

        if (preg_match('/^(?:[A-Z][A-Za-z0-9_]*\\\\)+[A-Z][A-Za-z0-9_]*$/', $provider) !== 1) {
            throw new InvalidArgumentException('Extension provider must be a fully qualified PHP class name.');
        }

        $authors = self::authors($data['authors'] ?? null);
        $permissions = self::allowedList($data['permissions'] ?? [], 'permission', $allowedPermissions);
        $uiSlots = self::allowedList($data['ui_slots'] ?? [], 'UI slot', $allowedUiSlots);
        [$migrationPath, $uninstallDataPolicy] = self::database($data['database'] ?? null);

        return new self(
            $id,
            $name,
            $version,
            $license,
            $authors,
            $core,
            $provider,
            $permissions,
            $uiSlots,
            $migrationPath,
            $uninstallDataPolicy,
        );
    }

    /** @param array<array-key, mixed> $data */
    private static function requiredString(array $data, string $key, int $maxLength): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Manifest field '{$key}' must be a non-empty string up to {$maxLength} characters.");
        }

        return trim($value);
    }

    /** @return list<array{name: string, url?: string}> */
    private static function authors(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            throw new InvalidArgumentException('Manifest authors must be a non-empty list.');
        }

        $authors = [];

        foreach ($value as $author) {
            if (! is_array($author)) {
                throw new InvalidArgumentException('Each manifest author must be an object.');
            }

            $name = $author['name'] ?? null;
            $url = $author['url'] ?? null;

            if (! is_string($name) || trim($name) === '' || mb_strlen($name) > 120) {
                throw new InvalidArgumentException('Each manifest author must have a valid name.');
            }

            $normalized = ['name' => trim($name)];

            if ($url !== null) {
                if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false || ! str_starts_with($url, 'https://')) {
                    throw new InvalidArgumentException('Manifest author URLs must use HTTPS.');
                }

                $normalized['url'] = $url;
            }

            $authors[] = $normalized;
        }

        return $authors;
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private static function allowedList(mixed $value, string $label, array $allowed): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException("Manifest {$label}s must be a list.");
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item) || ! in_array($item, $allowed, true)) {
                throw new InvalidArgumentException("Unknown extension {$label}: ".(is_scalar($item) ? (string) $item : 'invalid value'));
            }

            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    /** @return array{string|null, string} */
    private static function database(mixed $value): array
    {
        if ($value === null) {
            return [null, 'retain'];
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('Manifest database settings must be an object.');
        }

        $path = $value['migrations'] ?? null;
        $uninstallData = $value['uninstall_data'] ?? null;

        if (! is_string($path) || trim($path) === '' || mb_strlen($path) > 180) {
            throw new InvalidArgumentException('Manifest database migrations must be a relative directory path.');
        }

        $path = trim($path);
        $segments = explode('/', $path);

        if (str_starts_with($path, '/')
            || str_contains($path, '\\')
            || str_contains($path, "\0")
            || preg_match('/^[A-Za-z0-9._\/-]+$/', $path) !== 1
            || in_array('', $segments, true)
            || in_array('.', $segments, true)
            || in_array('..', $segments, true)) {
            throw new InvalidArgumentException('Manifest database migrations must stay inside the extension directory.');
        }

        if ($uninstallData !== 'retain') {
            throw new InvalidArgumentException("Manifest database uninstall_data must be 'retain'.");
        }

        return [$path, $uninstallData];
    }
}
