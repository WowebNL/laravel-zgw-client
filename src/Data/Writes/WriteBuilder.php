<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Writes;

use BackedEnum;
use DateTimeInterface;
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
     * Normalise an enum value to its backing scalar, preserving null.
     */
    protected function enum(BackedEnum|string|int|null $value): string|int|null
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
