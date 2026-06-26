<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use ReflectionProperty;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Data\Generated\Audittrail\AuditTrailData;
use Woweb\Zgw\Data\Generated\Audittrail\Enums\Bron;
use Woweb\Zgw\Data\Generated\Audittrail\Wijzigingen;
use Woweb\Zgw\Data\Generated\Zaken\ZaakData;
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

    public function test_store_returns_a_hydrated_dto(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response($this->zaak('new'), 201),
        ]);

        $zaak = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->store(['bronorganisatie' => '123456782']);

        $this->assertInstanceOf(ZaakData::class, $zaak);
        $this->assertSame('ZAAK-new', $zaak->identificatie);
    }

    public function test_patch_returns_a_hydrated_dto(): void
    {
        Http::fake([
            self::BASE.'zaken/p1' => Http::response($this->zaak('p1')),
        ]);

        $zaak = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->patch('p1', ['omschrijving' => 'Bijgewerkt']);

        $this->assertInstanceOf(ZaakData::class, $zaak);
        $this->assertSame('ZAAK-p1', $zaak->identificatie);
    }

    public function test_put_returns_a_hydrated_dto(): void
    {
        Http::fake([
            self::BASE.'zaken/r1' => Http::response($this->zaak('r1')),
        ]);

        $zaak = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->put('r1', ['bronorganisatie' => '123456782']);

        $this->assertInstanceOf(ZaakData::class, $zaak);
        $this->assertSame('ZAAK-r1', $zaak->identificatie);
    }

    public function test_delete_returns_true(): void
    {
        Http::fake([
            self::BASE.'zaken/d1' => Http::response('', 204),
        ]);

        $deleted = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->delete('d1');

        $this->assertTrue($deleted);
    }

    public function test_zoek_returns_a_lazy_collection_of_dtos(): void
    {
        Http::fake([
            self::BASE.'zaken/_zoek' => Http::response([
                'count' => 1,
                'next' => null,
                'previous' => null,
                'results' => [$this->zaak('z1')],
            ]),
        ]);

        $result = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->zoek(['zaakgeometrie' => []]);

        $this->assertInstanceOf(LazyCollection::class, $result);
        $first = $result->first();
        $this->assertInstanceOf(ZaakData::class, $first);
        $this->assertSame('ZAAK-z1', $first->identificatie);
    }

    public function test_audittrail_returns_typed_entries(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';

        Http::fake([
            self::BASE.'zaken/'.$uuid.'/audittrail' => Http::response([
                [
                    'uuid' => 'a0000000-0000-0000-0000-000000000001',
                    'bron' => 'zrc',
                    'actie' => 'create',
                    'aanmaakdatum' => '2024-03-01T10:00:00+00:00',
                    'hoofdObject' => self::BASE.'zaken/'.$uuid,
                    'wijzigingen' => ['oud' => null, 'nieuw' => ['toelichting' => 'x']],
                ],
                [
                    'uuid' => 'a0000000-0000-0000-0000-000000000002',
                    'bron' => 'zrc',
                    'actie' => 'update',
                    'aanmaakdatum' => '2024-03-02T11:00:00+00:00',
                ],
            ]),
        ]);

        $trail = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->audittrail($uuid);

        $this->assertCount(2, $trail);

        $first = $trail->first();
        $this->assertInstanceOf(AuditTrailData::class, $first);
        $this->assertSame(Bron::Zrc, $first->bron);
        $this->assertSame('create', $first->actie);
        $this->assertInstanceOf(CarbonImmutable::class, $first->aanmaakdatum);
        $this->assertInstanceOf(Reference::class, $first->hoofdObject);
        $this->assertInstanceOf(Wijzigingen::class, $first->wijzigingen);
        $this->assertSame(['toelichting' => 'x'], $first->wijzigingen->nieuw);
    }

    public function test_audittrail_item_returns_a_typed_entry(): void
    {
        $uuid = '11111111-1111-1111-1111-111111111111';
        $auditUuid = 'a0000000-0000-0000-0000-000000000001';

        Http::fake([
            self::BASE.'zaken/'.$uuid.'/audittrail/'.$auditUuid => Http::response([
                'uuid' => $auditUuid,
                'bron' => 'zrc',
                'actie' => 'create',
            ]),
        ]);

        $entry = Typed::wrap(Zgw::connection('main')->zaken()->zaken())->audittrailItem($uuid, $auditUuid);

        $this->assertInstanceOf(AuditTrailData::class, $entry);
        $this->assertSame($auditUuid, $entry->uuid);
        $this->assertSame('create', $entry->actie);
    }

    public function test_wrap_throws_for_an_unmapped_endpoint(): void
    {
        // An endpoint with no #[ZgwResource] mapping has no DTO, so wrapping it is a programming
        // error rather than a silent passthrough.
        $real = Zgw::connection('main')->zaken()->zaken();
        $connection = (new ReflectionProperty($real, 'connection'))->getValue($real);

        $unmapped = new class($connection) extends AbstractEndpoint {};

        $this->expectException(InvalidArgumentException::class);

        Typed::wrap($unmapped);
    }
}
