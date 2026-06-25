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

            $missing = array_values(array_diff($specFields, $declared));
            $orphan = array_values(array_diff($declared, $specFields));

            $this->assertSame([], $missing, "These {$source['schema']} spec fields have no property on {$dtoClass}: ".implode(', ', $missing));
            $this->assertSame([], $orphan, "These {$dtoClass} properties are not in any {$source['schema']} spec: ".implode(', ', $orphan));

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
        }

        return array_keys($fields);
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
