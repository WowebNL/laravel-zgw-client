<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract;

use ReflectionClass;
use Woweb\Zgw\Data\Casts\DiscriminatorCast;
use Woweb\Zgw\Data\Data;
use Woweb\Zgw\Tests\Contract\Support\GeneratedDtos;
use Woweb\Zgw\Tests\Contract\Support\ReleaseMatrix;

/**
 * Guards the generated polymorphic resolvers against the standard. For every discriminated schema
 * (Rol, ZaakObject) the generated DTO's DiscriminatorCast must key off the spec's discriminator
 * property, map only to values the spec actually defines, and resolve each to an existing DTO. A
 * fully typed resolver (no raw fallback) must cover every discriminator value, so a new subtype in
 * a future release turns this red instead of silently dropping data.
 */
class DiscriminatorCoverageTest extends ContractTestCase
{
    public function test_generated_discriminator_casts_match_the_specs(): void
    {
        $checked = 0;

        foreach (GeneratedDtos::all() as $dtoClass => $source) {
            $component = $source['component'];
            $schema = $source['schema'];

            $resolution = $this->newestResolution($component, $schema);

            if ($resolution === null) {
                continue;
            }

            $cast = $this->discriminatorCast($dtoClass, $resolution['field']);
            $this->assertInstanceOf(
                DiscriminatorCast::class,
                $cast,
                "{$dtoClass} is generated from the discriminated schema {$schema} but its {$resolution['field']} field has no DiscriminatorCast. Run `composer dto:generate`."
            );

            $this->assertSame(
                $resolution['property'],
                $cast->discriminatorField,
                "{$dtoClass} keys its discriminator off [{$cast->discriminatorField}] but the spec uses [{$resolution['property']}]."
            );

            $specValues = array_keys($resolution['map']);

            // No mapped value may be absent from the spec, and each must resolve to a real DTO.
            foreach ($cast->map as $value => $mappedDto) {
                $this->assertContains(
                    $value,
                    $specValues,
                    "{$dtoClass} maps discriminator value [{$value}], which the {$schema} spec does not define."
                );
                $this->assertTrue(
                    class_exists($mappedDto) && is_subclass_of($mappedDto, Data::class),
                    "{$dtoClass} maps [{$value}] to [{$mappedDto}], which is not a generated DTO."
                );
            }

            // A fully typed resolver (no raw fallback) must cover every value, otherwise a subtype
            // would be silently dropped. Only assert this against the complete cross-release set.
            if (! $cast->fallbackToRaw && $this->hasAllReleases($component)) {
                $missing = array_values(array_diff($specValues, array_keys($cast->map)));
                $this->assertSame(
                    [],
                    $missing,
                    "{$dtoClass} types no raw fallback but does not map these {$schema} discriminator values: ".implode(', ', $missing)
                );
            }

            $checked++;
        }

        if ($checked === 0) {
            $this->markTestSkipped('No discriminated DTO could be checked (no matching specs fetched).');
        }
    }

    /**
     * The discriminator resolution from the newest fetched release that defines the schema.
     *
     * @return array{property: string, field: string, map: array<string, string>}|null
     */
    private function newestResolution(string $component, string $schema): ?array
    {
        $resolution = null;

        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) === null) {
                continue;
            }

            $candidate = $this->loadSpec($release, $component)->discriminatorResolution($schema);

            if ($candidate !== null) {
                $resolution = $candidate;
            }
        }

        return $resolution;
    }

    /**
     * @param  class-string<Data>  $dtoClass
     */
    private function discriminatorCast(string $dtoClass, string $field): ?DiscriminatorCast
    {
        $method = (new ReflectionClass($dtoClass))->getMethod('casts');
        $method->setAccessible(true);

        /** @var array<string, mixed> $casts */
        $casts = $method->invoke(null);
        $cast = $casts[$field] ?? null;

        return $cast instanceof DiscriminatorCast ? $cast : null;
    }

    private function hasAllReleases(string $component): bool
    {
        foreach (array_keys(ReleaseMatrix::releases()) as $release) {
            if (ReleaseMatrix::specFile($release, $component) === null) {
                return false;
            }
        }

        return true;
    }
}
