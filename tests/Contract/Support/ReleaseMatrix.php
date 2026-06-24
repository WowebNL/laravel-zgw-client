<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract\Support;

/**
 * Reads tests/Contract/releases.json and locates the downloaded spec fixtures for each
 * ZGW release / component, so the contract tests and the coverage report share one source of truth.
 */
final class ReleaseMatrix
{
    public static function matrixPath(): string
    {
        return __DIR__.'/../releases.json';
    }

    public static function specsDir(): string
    {
        return dirname(__DIR__, 2).'/fixtures/specs';
    }

    /**
     * @return array<string, array{label: string, components: array<string, array{version: string, url: string}>}>
     */
    public static function releases(): array
    {
        /** @var array{releases: array<string, array{label: string, components: array<string, array{version: string, url: string}>}>} $matrix */
        $matrix = json_decode((string) file_get_contents(self::matrixPath()), true, 512, JSON_THROW_ON_ERROR);

        return $matrix['releases'];
    }

    /**
     * Path to a downloaded component spec fixture, or null when it has not been fetched.
     */
    public static function specFile(string $release, string $component): ?string
    {
        $path = self::specsDir()."/{$release}/{$component}.yaml";

        return is_file($path) && filesize($path) > 0 ? $path : null;
    }

    /**
     * Whether at least one spec fixture exists. Used to skip the suite when specs are absent
     * (so `composer test` still runs offline without fetching).
     */
    public static function specsAvailable(): bool
    {
        foreach (self::releases() as $release => $definition) {
            foreach (array_keys($definition['components']) as $component) {
                if (self::specFile($release, $component) !== null) {
                    return true;
                }
            }
        }

        return false;
    }
}
