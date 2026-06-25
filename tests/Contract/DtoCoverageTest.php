<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionProperty;
use Woweb\Zgw\Data\Data;
use Woweb\Zgw\Data\Generated\ZaakData;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Guards that each generated DTO stays in step with the specs: every field the specs define has a
 * home on the DTO, and the DTO carries no field the specs no longer define. When the specs change
 * and the DTOs are not regenerated, this turns into a red test with the exact field names.
 */
class DtoCoverageTest extends ContractTestCase
{
    /**
     * @param  class-string<Data>  $dtoClass
     */
    #[DataProvider('resources')]
    public function test_dto_matches_the_spec_schema(string $component, string $schema, string $dtoClass): void
    {
        $specFields = $this->unionOfSpecFields($component, $schema);

        if ($specFields === []) {
            $this->markTestSkipped("Schema [{$schema}] is not present in any fetched {$component} spec.");
        }

        $declared = $this->declaredFields($dtoClass);

        $missing = array_values(array_diff($specFields, $declared));
        $orphan = array_values(array_diff($declared, $specFields));

        $this->assertSame([], $missing, "These {$schema} spec fields have no DTO property: ".implode(', ', $missing));
        $this->assertSame([], $orphan, "These {$dtoClass} properties are not in any {$schema} spec: ".implode(', ', $orphan));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: class-string<Data>}>
     */
    public static function resources(): array
    {
        return [
            'Zaak' => ['zaken', 'Zaak', ZaakData::class],
        ];
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
     * @param  class-string<Data>  $dtoClass
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
