<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use SplFileInfo;
use Woweb\Zgw\Data\Data;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Checks the generated DTOs against the standard: every per-field version annotation must match
 * the field's actual presence across the pinned releases.
 *
 * For each field this asserts, bidirectionally (mirroring VersionAvailabilityTest for operations):
 * a field carries `@since ZGW X` exactly when it is absent before X and present from X; a field
 * carries no `@since` exactly when it exists in the oldest supported release; a field carries
 * `@deprecated Removed in ZGW Y` exactly when it stops existing from Y. The presence range must be
 * contiguous. So the DTOs' version metadata cannot silently drift from the specs.
 */
class DtoVersionMetadataTest extends ContractTestCase
{
    public function test_field_version_annotations_match_the_specs(): void
    {
        $checked = 0;

        foreach ($this->generatedDtos() as $class) {
            [$component, $schema] = $this->schemaOf($class);

            if ($component === null || $schema === null) {
                continue;
            }

            $releases = $this->releasesWithComponent($component);

            if (count($releases) < 2) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $field = $property->getName();

                if ($property->isStatic() || $field === 'extra' || $field === 'raw') {
                    continue;
                }

                $present = $this->releasesContaining($component, $schema, $field, $releases);

                if ($present === []) {
                    continue; // Coverage is the job of DtoCoverageTest.
                }

                $this->assertContiguous($present, $releases, "{$class}::\${$field}");

                $expectedSince = $present[0] === $releases[0] ? null : $present[0];
                $lastPresent = $present[count($present) - 1];
                $lastIndex = array_search($lastPresent, $releases, true);
                $expectedRemoved = $lastIndex === count($releases) - 1 ? null : $releases[$lastIndex + 1];

                [$actualSince, $actualRemoved] = $this->annotationsOf($property);

                $this->assertSame($expectedSince, $actualSince, "@since mismatch on {$class}::\${$field}");
                $this->assertSame($expectedRemoved, $actualRemoved, "@deprecated mismatch on {$class}::\${$field}");

                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected to check at least one generated DTO field.');
    }

    /**
     * @return list<class-string>
     */
    private function generatedDtos(): array
    {
        $dir = dirname(__DIR__, 2).'/src/Data/Generated';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        $classes = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1, -4);
            $class = 'Woweb\\Zgw\\Data\\Generated\\'.str_replace('/', '\\', $relative);

            if (class_exists($class) && is_subclass_of($class, Data::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return array{0: ?string, 1: ?string} component and schema name from the class @zgw-schema marker.
     */
    private function schemaOf(string $class): array
    {
        $doc = (new ReflectionClass($class))->getDocComment();

        if ($doc !== false && preg_match('/@zgw-schema\s+(\w+):(\w+)/', $doc, $m) === 1) {
            return [$m[1], $m[2]];
        }

        return [null, null];
    }

    /**
     * @return array{0: ?string, 1: ?string} the @since release and the @deprecated removed-in release.
     */
    private function annotationsOf(ReflectionProperty $property): array
    {
        $doc = $property->getDocComment();

        if ($doc === false) {
            return [null, null];
        }

        $since = preg_match('/@since ZGW (\S+)/', $doc, $m) === 1 ? $m[1] : null;
        $removed = preg_match('/@deprecated Removed in ZGW (\S+)/', $doc, $r) === 1 ? $r[1] : null;

        return [$since, $removed];
    }

    /**
     * @param  list<string>  $releases
     * @return list<string>
     */
    private function releasesContaining(string $component, string $schema, string $field, array $releases): array
    {
        $present = [];

        foreach ($releases as $release) {
            $spec = $this->loadSpec($release, $component);
            $schemaArray = $spec->componentSchema($schema);

            if ($schemaArray !== null && in_array($field, $spec->schemaProperties($schemaArray), true)) {
                $present[] = $release;
            }
        }

        return $present;
    }

    /**
     * @return list<string> the fetched releases that have this component, oldest first.
     */
    private function releasesWithComponent(string $component): array
    {
        $releases = [];

        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) !== null) {
                $releases[] = $release;
            }
        }

        sort($releases);

        return $releases;
    }

    /**
     * @param  list<string>  $present
     * @param  list<string>  $releases
     */
    private function assertContiguous(array $present, array $releases, string $where): void
    {
        $indices = array_map(static fn (string $r): int|false => array_search($r, $releases, true), $present);
        $span = range($indices[0], $indices[count($indices) - 1]);

        $this->assertSame($span, $indices, "Field presence is not contiguous across releases for {$where}.");
    }
}
