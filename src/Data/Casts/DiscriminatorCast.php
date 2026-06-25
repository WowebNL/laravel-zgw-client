<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use Woweb\Zgw\Data\Data;

/**
 * Casts a polymorphic sub-object into the typed DTO selected by a sibling discriminator field.
 *
 * ZGW models polymorphism with a discriminator: a field such as Rol.betrokkeneType or
 * ZaakObject.objectType decides the shape of a sibling sub-object (betrokkeneIdentificatie,
 * objectIdentificatie). This cast reads that discriminator value from the row and hydrates the
 * matching DTO from the map.
 *
 * It is deliberately tolerant. An unknown or missing discriminator value resolves to null, or, when
 * a raw fallback is allowed, the untouched sub-object array, so a discriminator value the map does
 * not type (for example a niche ZaakObject type) is preserved rather than dropped.
 */
final class DiscriminatorCast implements Cast, RowAware
{
    /**
     * @param  string  $discriminatorField  The sibling field whose value selects the subtype.
     * @param  array<string, class-string<Data>>  $map  Discriminator value to the DTO it hydrates.
     * @param  bool  $fallbackToRaw  When true, an unmapped value keeps the raw sub-object array
     *                               instead of resolving to null.
     */
    public function __construct(
        public readonly string $discriminatorField,
        public readonly array $map,
        public readonly bool $fallbackToRaw = false,
    ) {}

    /**
     * Without the row the subtype cannot be resolved, so keep the raw structure when a raw fallback
     * is allowed, otherwise null. Hydration always uses {@see self::castWithRow()} instead.
     */
    public function cast(mixed $value): mixed
    {
        return $this->fallbackToRaw && is_array($value) ? $value : null;
    }

    public function castWithRow(mixed $value, array $row): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $discriminator = $row[$this->discriminatorField] ?? null;

        if (is_string($discriminator) && isset($this->map[$discriminator])) {
            return ($this->map[$discriminator])::from($value);
        }

        return $this->fallbackToRaw ? $value : null;
    }
}
