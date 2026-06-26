<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Writes;

use BackedEnum;
use Carbon\CarbonInterval;
use DateInterval;
use DateTimeInterface;
use Woweb\Zgw\Data\Data;
use Woweb\Zgw\Data\Values\Reference;

/**
 * Base for the write builders that produce request payloads for create (POST/PUT) and update (PATCH).
 *
 * Read and write are deliberately separate. ZGW PATCH semantics distinguish an absent field (leave
 * the stored value untouched) from a null field (clear it), so a builder must never emit a field
 * the caller did not set. This is achieved by presence: a field appears in the payload only after
 * its setter is called. Calling a setter with null therefore clears the field on purpose, while a
 * field that is never set stays out of the payload entirely.
 */
abstract class WriteBuilder
{
    /**
     * Only fields the caller explicitly set, in insertion order.
     *
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return $this->values;
    }

    protected function set(string $field, mixed $value): static
    {
        $this->values[$field] = $value;

        return $this;
    }

    /**
     * Normalise a reference value (a Reference or a URL string) to its URL, preserving null.
     */
    protected function reference(Reference|string|null $value): ?string
    {
        return $value instanceof Reference ? $value->url : $value;
    }

    /**
     * Normalise a date value to a Y-m-d string, preserving null.
     */
    protected function date(DateTimeInterface|string|null $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    /**
     * Normalise a date-time value to an ISO 8601 string, preserving null.
     */
    protected function dateTime(DateTimeInterface|string|null $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : $value;
    }

    /**
     * Normalise an enum value to its backing scalar, preserving null.
     */
    protected function enum(BackedEnum|string|int|null $value): string|int|null
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * Normalise a duration value to an ISO 8601 duration string (for example "P30D"), preserving
     * null. This mirrors the read side, where a duration field hydrates into a CarbonInterval, so a
     * read duration drops straight back into a write.
     */
    protected function duration(DateInterval|string|null $value): ?string
    {
        return $value instanceof DateInterval ? CarbonInterval::instance($value)->spec() : $value;
    }

    /**
     * Set a polymorphic identification field from a value read off the API, so it round-trips into a
     * write without reaching for ->raw.
     *
     * ZGW models a Rol's betrokkeneIdentificatie and a ZaakObject's objectIdentificatie
     * polymorphically (the concrete shape is chosen by a sibling discriminator), so these fields
     * have no generated typed setter. Read back, the value is either the typed sub-DTO the
     * discriminator resolved or, for a type kept untyped, the raw array. This accepts both: a DTO is
     * reduced to its write-shape (the source fields only, see {@see Data::toWriteArray()}), an array
     * is passed through, and null clears the field.
     *
     * @param  Data|array<string, mixed>|null  $value
     */
    public function identification(string $field, Data|array|null $value): static
    {
        return $this->set($field, $value instanceof Data ? $value->toWriteArray() : $value);
    }
}
