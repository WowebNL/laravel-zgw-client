<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Concerns;

use ReflectionClass;
use ReflectionProperty;
use Woweb\Zgw\Data\Casts\Cast;
use Woweb\Zgw\Data\Casts\RowAware;

/**
 * Tolerant hydration of a DTO from a raw ZGW response array.
 *
 * The mapping is deliberately forgiving so a DTO survives spec drift:
 *
 * - A declared field missing from the response becomes null.
 * - A field present in the response but not declared on the DTO is preserved in $extra, so a value
 *   added by a newer ZGW release is never silently dropped before the DTO is regenerated.
 * - The untouched response is kept in $raw, so nothing is ever lost.
 *
 * Field names match one to one: a ZGW JSON key, the DTO property, and the cast key are all the
 * same camelCase string, so no name translation is needed.
 */
trait HydratesFromArray
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public static function from(array $raw): static
    {
        $reflection = new ReflectionClass(static::class);

        /** @var static $dto */
        $dto = $reflection->newInstanceWithoutConstructor();

        $casts = static::casts();
        $declared = ['extra' => true, 'raw' => true];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if ($name === 'extra' || $name === 'raw') {
                continue;
            }

            $declared[$name] = true;
            $value = $raw[$name] ?? null;

            if ($value !== null && isset($casts[$name])) {
                $cast = $casts[$name];
                // A row-aware cast (a polymorphic field that keys off a sibling) needs the whole
                // response; an ordinary cast only sees its own value.
                $value = $cast instanceof RowAware
                    ? $cast->castWithRow($value, $raw)
                    : $cast->cast($value);
            }

            $property->setValue($dto, $value);
        }

        $extra = [];

        foreach ($raw as $key => $value) {
            if (! isset($declared[$key])) {
                $extra[$key] = $value;
            }
        }

        $dto->extra = $extra;
        $dto->raw = $raw;

        return $dto;
    }

    /**
     * Map of field name to the cast that translates its raw value. Fields without a cast keep their
     * raw scalar value.
     *
     * @return array<string, Cast>
     */
    protected static function casts(): array
    {
        return [];
    }
}
