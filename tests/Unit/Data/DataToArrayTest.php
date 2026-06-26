<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Woweb\Zgw\Data\Generated\Zaken\ZaakData;

class DataToArrayTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function rawZaak(): array
    {
        return [
            'url' => 'https://example.com/zaken/api/v1/zaken/abc-123',
            'zaaktype' => 'https://example.com/catalogi/api/v1/zaaktypen/def-456',
            'identificatie' => 'ZAAK-2026-0001',
            'startdatum' => '2026-01-15',
            'registratiedatum' => '2026-01-15T10:30:00+00:00',
            'vertrouwelijkheidaanduiding' => 'openbaar',
            'einddatum' => null,
            'verlenging' => ['reden' => 'meer tijd nodig', 'duur' => 'P30D'],
            'kenmerken' => [
                ['kenmerk' => 'dossier-1', 'bron' => 'intern'],
                ['kenmerk' => 'dossier-2', 'bron' => 'extern'],
            ],
            'zaakgeometrie' => ['type' => 'Point', 'coordinates' => [4.9, 52.3]],
            'eenNieuwVeld' => 'kept-for-forward-compat',
        ];
    }

    public function test_to_array_reverses_the_casts_to_a_zgw_conformant_array(): void
    {
        $array = ZaakData::from($this->rawZaak())->toArray();

        // Reference back to its bare URL.
        $this->assertSame('https://example.com/zaken/api/v1/zaken/abc-123', $array['url']);
        $this->assertSame('https://example.com/catalogi/api/v1/zaaktypen/def-456', $array['zaaktype']);

        // Plain scalar untouched.
        $this->assertSame('ZAAK-2026-0001', $array['identificatie']);

        // Backed enum back to its value.
        $this->assertSame('openbaar', $array['vertrouwelijkheidaanduiding']);

        // A null field stays null.
        $this->assertNull($array['einddatum']);
    }

    public function test_to_array_keeps_a_date_a_date_and_a_date_time_a_date_time(): void
    {
        $array = ZaakData::from($this->rawZaak())->toArray();

        $this->assertSame('2026-01-15', $array['startdatum']);
        $this->assertSame('2026-01-15T10:30:00+00:00', $array['registratiedatum']);
    }

    public function test_to_array_recurses_into_nested_dtos_and_durations(): void
    {
        $array = ZaakData::from($this->rawZaak())->toArray();

        $this->assertSame(['reden' => 'meer tijd nodig', 'duur' => 'P30D'], $array['verlenging']);

        $this->assertSame([
            ['kenmerk' => 'dossier-1', 'bron' => 'intern'],
            ['kenmerk' => 'dossier-2', 'bron' => 'extern'],
        ], $array['kenmerken']);
    }

    public function test_to_array_returns_a_geometry_as_its_decoded_structure(): void
    {
        $array = ZaakData::from($this->rawZaak())->toArray();

        $this->assertSame(['type' => 'Point', 'coordinates' => [4.9, 52.3]], $array['zaakgeometrie']);
    }

    public function test_to_array_keeps_forward_compatible_fields(): void
    {
        $array = ZaakData::from($this->rawZaak())->toArray();

        $this->assertSame('kept-for-forward-compat', $array['eenNieuwVeld']);
    }

    public function test_json_encode_yields_the_same_structure_as_to_array(): void
    {
        $zaak = ZaakData::from($this->rawZaak());

        $this->assertSame($zaak->toArray(), json_decode((string) json_encode($zaak), true));
    }

    public function test_a_read_value_round_trips_back_into_a_dto(): void
    {
        $zaak = ZaakData::from($this->rawZaak());

        $again = ZaakData::from($zaak->toArray());

        $this->assertSame('openbaar', $again->vertrouwelijkheidaanduiding?->value);
        $this->assertSame('2026-01-15', $again->startdatum?->format('Y-m-d'));
        $this->assertSame($zaak->url?->url, $again->url?->url);
    }
}
