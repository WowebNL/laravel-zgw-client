<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data;

use BackedEnum;
use Carbon\CarbonInterval;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Woweb\Zgw\Data\Concerns\HydratesFromArray;
use Woweb\Zgw\Data\Values\GeoJsonGeometry;
use Woweb\Zgw\Data\Values\Reference;

/**
 * Base class for every read DTO.
 *
 * A DTO is a tolerant, structural snapshot of a ZGW resource. It carries no invariants and does no
 * I/O: it only moves data across the boundary from a response array into typed properties. Every
 * concrete DTO declares its public properties and, where a field needs translation, a cast.
 *
 * A DTO serialises back to a ZGW-conformant array through toArray() (and the same structure through
 * json_encode(), via JsonSerializable): references become their URL, enums their backing value,
 * dates and durations their ISO 8601 string, and nested DTOs recurse. This makes a read value
 * reusable in a write and lets a DTO be cached or handed to Filament/Livewire without reaching for
 * the raw response.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class Data implements Arrayable, JsonSerializable
{
    use HydratesFromArray;

    /**
     * Fields present in the response but not declared on this DTO (forward compatibility).
     *
     * @var array<string, mixed>
     */
    public array $extra = [];

    /**
     * The untouched response array this DTO was hydrated from.
     *
     * @var array<string, mixed>
     */
    public array $raw = [];

    /**
     * The declared properties as a ZGW-conformant array, with the casts reversed (Reference to URL,
     * enum to value, date and duration to their ISO 8601 string, nested DTO to array). Fields kept
     * in $extra are appended verbatim, so a round-trip through toArray() drops nothing.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [];

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if ($name === 'extra' || $name === 'raw') {
                continue;
            }

            $out[$name] = self::normaliseForArray($property->getValue($this), $this->raw[$name] ?? null);
        }

        foreach ($this->extra as $key => $value) {
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * The write-shape of this DTO: like toArray(), but limited to the fields that were actually
     * present in the source response, so a value read from the API round-trips into a write
     * identical to the source rather than emitting every declared field as null.
     *
     * This is the clean way to copy a polymorphic identification (a Rol's betrokkeneIdentificatie,
     * a ZaakObject's objectIdentificatie) verbatim into a new write, without reaching for ->raw.
     * Nested DTOs are write-shaped too.
     *
     * @return array<string, mixed>
     */
    public function toWriteArray(): array
    {
        $out = [];

        foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            if ($name === 'extra' || $name === 'raw') {
                continue;
            }

            // Only fields seen on the wire, so an absent field is left out rather than cleared.
            if (! array_key_exists($name, $this->raw)) {
                continue;
            }

            $out[$name] = self::normaliseForArray($property->getValue($this), $this->raw[$name], writeShape: true);
        }

        foreach ($this->extra as $key => $value) {
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Reverse the casts on a single value back to its ZGW wire form.
     *
     * @param  mixed  $rawHint  The original wire value for this field, used only to keep a date
     *                          field a date rather than promoting it to a date-time.
     * @param  bool  $writeShape  When true a nested DTO is reduced to its write-shape (only the
     *                            fields present in its source) rather than its full snapshot.
     */
    private static function normaliseForArray(mixed $value, mixed $rawHint = null, bool $writeShape = false): mixed
    {
        if ($value instanceof self) {
            return $writeShape ? $value->toWriteArray() : $value->toArray();
        }

        if ($value instanceof Reference) {
            return $value->url;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof GeoJsonGeometry) {
            return $value->value;
        }

        if ($value instanceof CarbonInterval) {
            return $value->spec();
        }

        if ($value instanceof DateTimeInterface) {
            // ZGW has both date (Y-m-d) and date-time fields, and both hydrate to a CarbonImmutable.
            // The shape of the original wire value tells the two apart, so a date stays a date.
            return is_string($rawHint) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawHint) === 1
                ? $value->format('Y-m-d')
                : $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::normaliseForArray($item, null, $writeShape), $value);
        }

        return $value;
    }
}
