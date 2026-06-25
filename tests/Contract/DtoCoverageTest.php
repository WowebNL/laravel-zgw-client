<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use ReflectionClass;
use ReflectionProperty;
use Woweb\Zgw\Tests\Contract\Support\GeneratedDtos;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Guards that each generated DTO stays in step with the specs: every field the specs define (across
 * the releases present) has a home on the DTO, and the DTO carries no field the specs no longer
 * define. When the specs change and the DTOs are not regenerated, this turns into a red test with
 * the exact field names.
 */
class DtoCoverageTest extends ContractTestCase
{
    public function test_every_generated_dto_matches_its_spec_schema(): void
    {
        $checked = 0;

        foreach (GeneratedDtos::all() as $dtoClass => $source) {
            $specFields = $this->unionOfSpecFields($source['component'], $source['schema']);

            if ($specFields === []) {
                continue; // This component's specs are not present in this job.
            }

            $declared = $this->declaredFields($dtoClass);

            // Every field the fetched specs define must have a DTO property. This holds per release.
            $missing = array_values(array_diff($specFields, $declared));
            $this->assertSame([], $missing, "These {$source['schema']} spec fields have no property on {$dtoClass}: ".implode(', ', $missing));

            // The reverse (no DTO property without a spec field) is only valid against the full
            // cross-release union: a field added in a later release would look orphaned in a job
            // that only fetched an earlier release. So only assert it when all releases are present.
            if ($this->hasAllReleases($source['component'])) {
                $orphan = array_values(array_diff($declared, $specFields));
                $this->assertSame([], $orphan, "These {$dtoClass} properties are not in any {$source['schema']} spec: ".implode(', ', $orphan));
            }

            $checked++;
        }

        if ($checked === 0) {
            $this->markTestSkipped('No generated DTO could be checked (no matching specs fetched).');
        }
    }

    /**
     * The union of the schema's property names across every fetched release (the DTO is a superset).
     *
     * @return list<string>
     */
    private function unionOfSpecFields(string $component, string $schema): array
    {
        $fields = [];

        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) === null) {
                continue;
            }

            $spec = $this->loadSpec($release, $component);
            $schemaArray = $spec->componentSchema($schema);

            if ($schemaArray === null) {
                continue;
            }

            foreach ($spec->schemaProperties($schemaArray) as $name) {
                $fields[$name] = true;
            }

            // A discriminated schema (Rol, ZaakObject) carries a polymorphic sub-object field that
            // lives only on its subtypes, so it is not in schemaProperties but is a real DTO field.
            $resolution = $spec->discriminatorResolution($schema);
            if ($resolution !== null) {
                $fields[$resolution['field']] = true;
            }
        }

        return array_keys($fields);
    }

    /**
     * Whether every declared release has this component's spec fetched, so the cross-release union
     * of fields is complete (and the orphan check is meaningful).
     */
    private function hasAllReleases(string $component): bool
    {
        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  class-string  $dtoClass
     * @return list<string>
     */
    private function declaredFields(string $dtoClass): array
    {
        $fields = [];

        foreach ((new ReflectionClass($dtoClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if (! $property->isStatic() && $name !== 'extra' && $name !== 'raw') {
                $fields[] = $name;
            }
        }

        return $fields;
    }
}
