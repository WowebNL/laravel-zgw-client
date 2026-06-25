<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use BackedEnum;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Woweb\Zgw\Data\Casts\DateTimeCast;
use Woweb\Zgw\Data\Casts\DiscriminatorCast;
use Woweb\Zgw\Data\Casts\DtoCast;
use Woweb\Zgw\Data\Casts\DtoCollectionCast;
use Woweb\Zgw\Data\Casts\DurationCast;
use Woweb\Zgw\Data\Casts\EnumCast;
use Woweb\Zgw\Data\Casts\GeoJsonCast;
use Woweb\Zgw\Data\Casts\ReferenceCast;
use Woweb\Zgw\Data\Data;
use Woweb\Zgw\Tests\Contract\Support\GeneratedDtos;

/**
 * Exercises the hydration pipeline across every generated DTO at once, rather than hand-writing a
 * test per DTO. For each DTO it builds a synthetic payload from the field's cast and declared type,
 * hydrates, and asserts the result. This drives `from()` and every cast on its happy path for all
 * generated DTOs, and turns red if a regenerated DTO stops hydrating.
 */
class GeneratedDtoHydrationTest extends TestCase
{
    public function test_every_generated_dto_hydrates_from_a_synthetic_payload(): void
    {
        $checked = 0;

        foreach (array_keys(GeneratedDtos::all()) as $class) {
            $payload = $this->syntheticPayload($class);

            $dto = $class::from($payload);
            $this->assertInstanceOf($class, $dto);
            $this->assertSame($payload, $dto->raw, "raw should round-trip the payload for {$class}");

            $checked++;
        }

        $this->assertGreaterThan(50, $checked, 'Expected the full set of generated DTOs to be checked.');
    }

    public function test_every_generated_dto_hydrates_from_an_empty_payload(): void
    {
        foreach (array_keys(GeneratedDtos::all()) as $class) {
            $dto = $class::from([]);

            $this->assertInstanceOf($class, $dto);
            $this->assertSame([], $dto->extra, "an empty payload yields no extra fields for {$class}");
        }
    }

    /**
     * @param  class-string<Data>  $class
     * @return array<string, mixed>
     */
    private function syntheticPayload(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $casts = $this->castsOf($class);

        $payload = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if ($property->isStatic() || $name === 'extra' || $name === 'raw') {
                continue;
            }

            $payload[$name] = $this->valueFor($property, $casts[$name] ?? null);
        }

        // A discriminated field needs its sibling discriminator set to a value the cast maps, so the
        // subtype actually resolves.
        foreach ($casts as $cast) {
            if ($cast instanceof DiscriminatorCast && $cast->map !== []) {
                $payload[$cast->discriminatorField] = array_key_first($cast->map);
            }
        }

        return $payload;
    }

    private function valueFor(ReflectionProperty $property, mixed $cast): mixed
    {
        if ($cast instanceof ReferenceCast) {
            return 'https://example.com/api/v1/resource/123e4567-e89b-12d3-a456-426614174000';
        }
        if ($cast instanceof DateTimeCast) {
            return '2024-01-01T12:00:00+00:00';
        }
        if ($cast instanceof DurationCast) {
            return 'P1D';
        }
        if ($cast instanceof GeoJsonCast) {
            return ['type' => 'Point', 'coordinates' => [0, 0]];
        }
        if ($cast instanceof DtoCollectionCast) {
            return [[]];
        }
        if ($cast instanceof DtoCast || $cast instanceof DiscriminatorCast) {
            return [];
        }
        if ($cast instanceof EnumCast) {
            return $this->firstEnumValue($property);
        }

        // No cast: a value matching the declared scalar or array type.
        $type = $property->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : 'string';

        return match ($name) {
            'int' => 1,
            'float' => 1.0,
            'bool' => true,
            'array' => [],
            'mixed' => [],
            default => 'value',
        };
    }

    private function firstEnumValue(ReflectionProperty $property): string|int
    {
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $enum = $type->getName();

            if (is_subclass_of($enum, BackedEnum::class)) {
                return $enum::cases()[0]->value;
            }
        }

        return 'value';
    }

    /**
     * @param  class-string<Data>  $class
     * @return array<string, object>
     */
    private function castsOf(string $class): array
    {
        $method = (new ReflectionClass($class))->getMethod('casts');
        $method->setAccessible(true);

        /** @var array<string, object> $casts */
        $casts = $method->invoke(null);

        return $casts;
    }
}
