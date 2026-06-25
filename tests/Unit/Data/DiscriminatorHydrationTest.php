<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Generated\Zaken\ObjectAdres;
use Woweb\Zgw\Data\Generated\Zaken\RolData;
use Woweb\Zgw\Data\Generated\Zaken\RolMedewerker;
use Woweb\Zgw\Data\Generated\Zaken\RolNatuurlijkPersoon;
use Woweb\Zgw\Data\Generated\Zaken\ZaakObjectData;

/**
 * The polymorphic identification sub-object of Rol and ZaakObject hydrates into the typed DTO the
 * sibling discriminator selects. Rol types all subtypes and resolves an unknown value to null;
 * ZaakObject types only the common object types and keeps every other value as a raw array.
 */
class DiscriminatorHydrationTest extends TestCase
{
    public function test_rol_resolves_betrokkene_identificatie_by_betrokkene_type(): void
    {
        $rol = RolData::from([
            'betrokkeneType' => 'natuurlijk_persoon',
            'betrokkeneIdentificatie' => [
                'inpBsn' => '111222333',
                'geslachtsnaam' => 'Jansen',
            ],
        ]);

        $this->assertInstanceOf(RolNatuurlijkPersoon::class, $rol->betrokkeneIdentificatie);
        $this->assertSame('111222333', $rol->betrokkeneIdentificatie->inpBsn);
        $this->assertSame('Jansen', $rol->betrokkeneIdentificatie->geslachtsnaam);
    }

    public function test_rol_resolves_a_different_subtype_for_a_different_discriminator(): void
    {
        $rol = RolData::from([
            'betrokkeneType' => 'medewerker',
            'betrokkeneIdentificatie' => ['identificatie' => 'EMP-1', 'achternaam' => 'De Vries'],
        ]);

        $this->assertInstanceOf(RolMedewerker::class, $rol->betrokkeneIdentificatie);
        $this->assertSame('EMP-1', $rol->betrokkeneIdentificatie->identificatie);
    }

    public function test_rol_with_an_unknown_betrokkene_type_resolves_to_null(): void
    {
        $rol = RolData::from([
            'betrokkeneType' => 'iets_nieuws_uit_1_8',
            'betrokkeneIdentificatie' => ['identificatie' => 'X'],
        ]);

        $this->assertNull($rol->betrokkeneIdentificatie);
    }

    public function test_rol_without_a_betrokkene_type_resolves_to_null(): void
    {
        $rol = RolData::from([
            'betrokkeneIdentificatie' => ['identificatie' => 'X'],
        ]);

        $this->assertNull($rol->betrokkeneIdentificatie);
    }

    public function test_zaakobject_types_a_common_object_type(): void
    {
        $object = ZaakObjectData::from([
            'objectType' => 'adres',
            'objectIdentificatie' => [
                'identificatie' => '0363200000000001',
                'postcode' => '1011AB',
                'huisnummer' => 1,
            ],
        ]);

        $this->assertInstanceOf(ObjectAdres::class, $object->objectIdentificatie);
        $this->assertSame('1011AB', $object->objectIdentificatie->postcode);
        $this->assertSame(1, $object->objectIdentificatie->huisnummer);
    }

    public function test_zaakobject_keeps_an_untyped_object_type_as_a_raw_array(): void
    {
        // "wegdeel" is a valid objectType the common set deliberately leaves untyped, so its
        // identification is preserved as a raw array rather than dropped.
        $identification = ['identificatie' => 'WEG-1', 'iets' => 'specifieks'];

        $object = ZaakObjectData::from([
            'objectType' => 'wegdeel',
            'objectIdentificatie' => $identification,
        ]);

        $this->assertIsArray($object->objectIdentificatie);
        $this->assertSame($identification, $object->objectIdentificatie);
    }

    public function test_zaakobject_with_an_unknown_object_type_keeps_the_raw_array(): void
    {
        $object = ZaakObjectData::from([
            'objectType' => 'iets_nieuws_uit_1_8',
            'objectIdentificatie' => ['x' => 'y'],
        ]);

        $this->assertSame(['x' => 'y'], $object->objectIdentificatie);
    }
}
