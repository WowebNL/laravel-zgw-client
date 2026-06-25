<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use ReflectionClass;
use ReflectionProperty;
use Woweb\Zgw\Tests\Contract\Support\GeneratedDtos;
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

        foreach (GeneratedDtos::all() as $class => $source) {
            $releases = $this->releasesWithComponent($source['component']);

            if (count($releases) < 2) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $field = $property->getName();

                if ($property->isStatic() || $field === 'extra' || $field === 'raw') {
                    continue;
                }

                $present = $this->releasesContaining($source['component'], $source['schema'], $field, $releases);

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

        if ($checked === 0) {
            // The per-release contract matrix fetches one release at a time; verifying version
            // metadata needs at least two releases of a component present (the all-releases job).
            $this->markTestSkipped('Need at least two releases of a component present to verify version metadata.');
        }
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

            if ($schemaArray === null) {
                continue;
            }

            if (in_array($field, $spec->schemaProperties($schemaArray), true)) {
                $present[] = $release;

                continue;
            }

            // The polymorphic sub-object field of a discriminated schema is present in a release
            // exactly when that release's schema is discriminated.
            $resolution = $spec->discriminatorResolution($schema);
            if ($resolution !== null && $resolution['field'] === $field) {
                $present[] = $release;

                continue;
            }

            // The _expand field is present in a release exactly when that release has the
            // {Schema}Expanded variant.
            if ($field === '_expand' && $spec->expandResolution($schema) !== null) {
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
