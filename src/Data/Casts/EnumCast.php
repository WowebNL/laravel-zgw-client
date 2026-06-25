<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use BackedEnum;

/**
 * Casts a scalar into a backed enum case via tryFrom, so an unknown value (a new case added by a
 * later ZGW release) becomes null instead of throwing. This keeps the DTO forward compatible.
 */
final class EnumCast implements Cast
{
    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public function __construct(
        private readonly string $enum,
    ) {}

    public function cast(mixed $value): ?BackedEnum
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        return ($this->enum)::tryFrom($value);
    }
}
