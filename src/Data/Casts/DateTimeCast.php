<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Casts an ISO 8601 date or date-time string to a CarbonImmutable, or null when it cannot be parsed.
 */
final class DateTimeCast implements Cast
{
    public function cast(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
