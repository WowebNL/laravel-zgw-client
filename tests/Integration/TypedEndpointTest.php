<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Woweb\Zgw\Data\Generated\ZaakData;
use Woweb\Zgw\Data\Typed;
use Woweb\Zgw\Data\Values\Reference;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class TypedEndpointTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    /**
     * @return array<string, mixed>
     */
    private function zaak(string $uuid): array
    {
        return [
            'url' => self::BASE.'zaken/'.$uuid,
            'uuid' => $uuid,
            'identificatie' => 'ZAAK-'.$uuid,
            'startdatum' => '2024-03-01',
            'vertrouwelijkheidaanduiding' => 'openbaar',
        ];
    }

    public function test_show_returns_a_hydrated_dto(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';

        Http::fake([
            self::BASE.'zaken/'.$uuid => Http::response($this->zaak($uuid)),
        ]);

        $zaak = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->show($uuid);

        $this->assertInstanceOf(ZaakData::class, $zaak);
        $this->assertSame('ZAAK-'.$uuid, $zaak->identificatie);
        $this->assertInstanceOf(Reference::class, $zaak->url);
    }

    public function test_index_returns_a_lazy_collection_of_dtos(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [
                    $this->zaak('a1'),
                    $this->zaak('a2'),
                ],
            ]),
        ]);

        $result = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->index();

        $this->assertInstanceOf(LazyCollection::class, $result);

        $first = $result->first();
        $this->assertInstanceOf(ZaakData::class, $first);
        $this->assertSame('ZAAK-a1', $first->identificatie);
    }

    public function test_array_passthrough_stays_available(): void
    {
        Http::fake([
            self::BASE.'zaken/x' => Http::response($this->zaak('x')),
        ]);

        $raw = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->endpoint()->show('x');

        $this->assertIsArray($raw);
        $this->assertSame('ZAAK-x', $raw['identificatie']);
    }
}
