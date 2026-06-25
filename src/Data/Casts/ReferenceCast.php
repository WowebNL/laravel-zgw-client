<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use Woweb\Zgw\Data\Values\Reference;

/**
 * Casts a URL string into a Reference value object, or null when the value is not a usable URL.
 */
final class ReferenceCast implements Cast
{
    public function cast(mixed $value): ?Reference
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return new Reference($value);
    }
}
