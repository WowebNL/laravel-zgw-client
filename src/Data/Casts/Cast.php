<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

/**
 * Translates a raw value from a ZGW response array into a typed value on a DTO.
 *
 * Casts are tolerant: a value that cannot be understood is turned into null rather than
 * throwing, so a single unexpected value never breaks hydration of the whole resource.
 */
interface Cast
{
    public function cast(mixed $value): mixed;
}
