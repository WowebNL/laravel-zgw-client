<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Generated\Zaken\Enums\AardRelatie;
use Woweb\Zgw\Data\Generated\Zaken\Enums\Archiefnominatie;
use Woweb\Zgw\Data\Generated\Zaken\Enums\Vertrouwelijkheidaanduiding;
use Woweb\Zgw\Data\Generated\Zaken\RelevanteZaak;
use Woweb\Zgw\Data\Generated\Zaken\Verlenging;
use Woweb\Zgw\Data\Generated\Zaken\ZaakData;
use Woweb\Zgw\Data\Values\GeoJsonGeometry;
use Woweb\Zgw\Data\Values\Reference;

class ZaakDataHydrationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'url' => 'https://zaken.example.com/zaken/api/v1/zaken/2b7e1c0e-0000-0000-0000-000000000001',
            'uuid' => '2b7e1c0e-0000-0000-0000-000000000001',
            'identificatie' => 'ZAAK-2024-0001',
            'bronorganisatie' => '123456782',
            'omschrijving' => 'Een testzaak',
            'zaaktype' => 'https://catalogi.example.com/catalogi/api/v1/zaaktypen/abc',
            'registratiedatum' => '2024-01-15',
            'startdatum' => '2024-01-15',
            'laatsteBetaaldatum' => '2024-02-01T12:30:00Z',
            'vertrouwelijkheidaanduiding' => 'openbaar',
            'productenOfDiensten' => ['https://example.com/product/1'],
            'verlenging' => ['reden' => 'Meer tijd nodig', 'duur' => 'P30D'],
            'relevanteAndereZaken' => [
                ['url' => 'https://zaken.example.com/zaken/api/v1/zaken/other', 'aardRelatie' => 'vervolg'],
            ],
            'kenmerken' => [
                ['kenmerk' => 'dossier-1', 'bron' => 'intern'],
            ],
            'rollen' => [
                'https://zaken.example.com/zaken/api/v1/rollen/r1',
            ],
            'archiefnominatie' => 'vernietigen',
            'zaakgeometrie' => ['type' => 'Point', 'coordinates' => [4.9, 52.37]],
        ], $overrides);
    }

    public function test_hydrates_geojson_geometry_as_a_value_object(): void
    {
        $zaak = ZaakData::from($this->payload());

        $this->assertInstanceOf(GeoJsonGeometry::class, $zaak->zaakgeometrie);
        $this->assertSame('Point', $zaak->zaakgeometrie->type());
        $this->assertSame([4.9, 52.37], $zaak->zaakgeometrie->coordinates());
    }

    public function test_hydrates_scalars_references_and_dates(): void
    {
        $zaak = ZaakData::from($this->payload());

        $this->assertSame('ZAAK-2024-0001', $zaak->identificatie);
        $this->assertInstanceOf(Reference::class, $zaak->url);
        $this->assertSame('2b7e1c0e-0000-0000-0000-000000000001', $zaak->url->uuid());
        $this->assertInstanceOf(CarbonImmutable::class, $zaak->registratiedatum);
        $this->assertSame('2024-01-15', $zaak->registratiedatum->format('Y-m-d'));
        $this->assertInstanceOf(CarbonImmutable::class, $zaak->laatsteBetaaldatum);
    }

    public function test_hydrates_enums_with_tolerant_fallback(): void
    {
        $zaak = ZaakData::from($this->payload());
        $this->assertSame(Vertrouwelijkheidaanduiding::Openbaar, $zaak->vertrouwelijkheidaanduiding);
        $this->assertSame(Archiefnominatie::Vernietigen, $zaak->archiefnominatie);

        $unknown = ZaakData::from($this->payload(['vertrouwelijkheidaanduiding' => 'iets_nieuws_uit_1_8']));
        $this->assertNull($unknown->vertrouwelijkheidaanduiding);
    }

    public function test_hydrates_nested_dto_with_duration(): void
    {
        $zaak = ZaakData::from($this->payload());

        $this->assertInstanceOf(Verlenging::class, $zaak->verlenging);
        $this->assertSame('Meer tijd nodig', $zaak->verlenging->reden);
        $this->assertInstanceOf(CarbonInterval::class, $zaak->verlenging->duur);
        $this->assertSame(30, $zaak->verlenging->duur->d);
    }

    public function test_hydrates_dto_collections(): void
    {
        $zaak = ZaakData::from($this->payload());

        $this->assertIsArray($zaak->relevanteAndereZaken);
        $this->assertCount(1, $zaak->relevanteAndereZaken);
        $this->assertInstanceOf(RelevanteZaak::class, $zaak->relevanteAndereZaken[0]);
        $this->assertSame(AardRelatie::Vervolg, $zaak->relevanteAndereZaken[0]->aardRelatie);
        $this->assertInstanceOf(Reference::class, $zaak->relevanteAndereZaken[0]->url);

        $this->assertCount(1, $zaak->kenmerken);
        $this->assertSame('dossier-1', $zaak->kenmerken[0]->kenmerk);
    }

    public function test_missing_fields_become_null(): void
    {
        $zaak = ZaakData::from(['identificatie' => 'ZAAK-X']);

        $this->assertSame('ZAAK-X', $zaak->identificatie);
        $this->assertNull($zaak->einddatum);
        $this->assertNull($zaak->verlenging);
        $this->assertNull($zaak->vertrouwelijkheidaanduiding);
    }

    public function test_unknown_fields_are_kept_in_extra_and_raw(): void
    {
        $zaak = ZaakData::from($this->payload(['eenNieuwVeld' => 'waarde-uit-toekomstige-release']));

        $this->assertArrayHasKey('eenNieuwVeld', $zaak->extra);
        $this->assertSame('waarde-uit-toekomstige-release', $zaak->extra['eenNieuwVeld']);
        $this->assertArrayHasKey('identificatie', $zaak->raw);
        $this->assertArrayNotHasKey('identificatie', $zaak->extra);
    }
}
