<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use Woweb\Zgw\Data\Data;

/**
 * Casts a nested object into a typed DTO, or null when the value is not an object array.
 */
final class DtoCast implements Cast
{
    /**
     * @param  class-string<Data>  $dto
     */
    public function __construct(
        private readonly string $dto,
    ) {}

    public function cast(mixed $value): ?Data
    {
        if (! is_array($value)) {
            return null;
        }

        return ($this->dto)::from($value);
    }
}
