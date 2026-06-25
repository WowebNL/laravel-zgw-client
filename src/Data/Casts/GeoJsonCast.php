<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use Woweb\Zgw\Data\Values\GeoJsonGeometry;

/**
 * Casts a decoded GeoJSON object into a GeoJsonGeometry value object, or null when it is not an
 * object array.
 */
final class GeoJsonCast implements Cast
{
    public function cast(mixed $value): ?GeoJsonGeometry
    {
        if (! is_array($value)) {
            return null;
        }

        return new GeoJsonGeometry($value);
    }
}
