<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Generated\Zaken\RolData;
use Woweb\Zgw\Data\Generated\Zaken\ZaakObjectData;
use Woweb\Zgw\Data\Writes\Zaken\RolWrite;
use Woweb\Zgw\Data\Writes\Zaken\ZaakObjectWrite;

/**
 * A polymorphic identification read off the API (a Rol's betrokkeneIdentificatie, a ZaakObject's
 * objectIdentificatie) round-trips into a new write without reaching for ->raw, and the resulting
 * body is identical to the source.
 */
class DiscriminatorRoundTripTest extends TestCase
{
    public function test_to_write_array_keeps_only_the_fields_present_in_the_source(): void
    {
        $source = [
            'inpBsn' => '111222333',
            'geslachtsnaam' => 'Jansen',
            'verblijfsadres' => ['aoaIdentificatie' => 'X', 'wplWoonplaatsNaam' => 'Plaats'],
        ];

        $rol = RolData::from([
            'betrokkeneType' => 'natuurlijk_persoon',
            'betrokkeneIdentificatie' => $source,
        ]);

        // toArray() would add every declared field as null; toWriteArray() stays identical to source,
        // and a nested DTO is write-shaped too.
        $this->assertSame($source, $rol->betrokkeneIdentificatie->toWriteArray());
    }

    public function test_typed_identification_round_trips_into_a_write_identical_to_the_source(): void
    {
        $source = ['inpBsn' => '111222333', 'geslachtsnaam' => 'Jansen'];

        $rol = RolData::from([
            'betrokkeneType' => 'natuurlijk_persoon',
            'roltype' => 'https://example.com/catalogi/api/v1/roltypen/abc',
            'betrokkeneIdentificatie' => $source,
        ]);

        $payload = (new RolWrite)
            ->zaak('https://example.com/zaken/api/v1/zaken/target')
            ->betrokkeneType($rol->betrokkeneType)
            ->roltype($rol->roltype)
            ->identification('betrokkeneIdentificatie', $rol->betrokkeneIdentificatie)
            ->toPayload();

        $this->assertSame($source, $payload['betrokkeneIdentificatie']);
        $this->assertSame('natuurlijk_persoon', $payload['betrokkeneType']);
        $this->assertSame('https://example.com/zaken/api/v1/zaken/target', $payload['zaak']);
    }

    public function test_an_untyped_identification_kept_as_a_raw_array_round_trips_too(): void
    {
        $source = ['identificatie' => 'WEG-1', 'iets' => 'specifieks'];

        $object = ZaakObjectData::from([
            'objectType' => 'wegdeel',
            'objectIdentificatie' => $source,
        ]);

        // For an untyped objectType the read value is already a raw array; the helper passes it through.
        $payload = (new ZaakObjectWrite)
            ->identification('objectIdentificatie', $object->objectIdentificatie)
            ->toPayload();

        $this->assertSame($source, $payload['objectIdentificatie']);
    }

    public function test_identification_accepts_a_plain_array_and_null(): void
    {
        $payload = (new RolWrite)
            ->identification('betrokkeneIdentificatie', ['inpBsn' => '999'])
            ->toPayload();
        $this->assertSame(['inpBsn' => '999'], $payload['betrokkeneIdentificatie']);

        // Passing null clears the field deliberately (present in the payload as null).
        $cleared = (new RolWrite)
            ->identification('betrokkeneIdentificatie', null)
            ->toPayload();
        $this->assertArrayHasKey('betrokkeneIdentificatie', $cleared);
        $this->assertNull($cleared['betrokkeneIdentificatie']);
    }
}
