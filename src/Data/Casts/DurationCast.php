<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Casts;

use Carbon\CarbonInterval;
use DateInterval;
use Throwable;

/**
 * Casts an ISO 8601 duration string (for example "P30D") to a CarbonInterval, or null when it
 * cannot be parsed. ZGW uses durations for fields such as a verlenging (extension) period.
 */
final class DurationCast implements Cast
{
    public function cast(mixed $value): ?CarbonInterval
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonInterval::instance(new DateInterval($value));
        } catch (Throwable) {
            return null;
        }
    }
}
