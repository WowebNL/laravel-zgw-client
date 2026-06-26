<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use DateInterval;
use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Casts\DurationCast;
use Woweb\Zgw\Data\Writes\Catalogi\ZaakTypeWrite;

/**
 * A duration write field normalises a DateInterval (and the CarbonInterval a read produces) to its
 * ISO 8601 string, the counterpart of the DurationCast on the read side.
 */
class WriteDurationNormalisationTest extends TestCase
{
    public function test_a_date_interval_is_normalised_to_an_iso_8601_duration(): void
    {
        $payload = (new ZaakTypeWrite)->doorlooptijd(new DateInterval('P30D'))->toPayload();

        $this->assertSame('P30D', $payload['doorlooptijd']);
    }

    public function test_a_string_duration_is_passed_through_unchanged(): void
    {
        $payload = (new ZaakTypeWrite)->servicenorm('P14D')->toPayload();

        $this->assertSame('P14D', $payload['servicenorm']);
    }

    public function test_a_read_duration_round_trips_into_a_write(): void
    {
        // The read side hydrates a duration into a CarbonInterval; it must drop straight back into a
        // write without the caller formatting it by hand.
        $read = (new DurationCast)->cast('P1Y2M10D');

        $payload = (new ZaakTypeWrite)->doorlooptijd($read)->toPayload();

        $this->assertSame('P1Y2M10D', $payload['doorlooptijd']);
    }

    public function test_null_clears_the_field_deliberately(): void
    {
        $payload = (new ZaakTypeWrite)->doorlooptijd(null)->toPayload();

        $this->assertArrayHasKey('doorlooptijd', $payload);
        $this->assertNull($payload['doorlooptijd']);
    }
}
