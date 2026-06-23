<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Api\Endpoints\Besluiten\Besluiten;
use Woweb\Zgw\Api\Endpoints\Besluiten\Besluitinformatieobjecten;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class BesluitenApiTest extends TestCase
{
    private const BASE = 'https://besluiten.example.com/besluiten/api/v1/';

    public function test_endpoint_accessors_return_correct_instances(): void
    {
        $besluiten = Zgw::connection('main')->besluiten();

        $this->assertInstanceOf(Besluiten::class, $besluiten->besluiten());
        $this->assertInstanceOf(Besluitinformatieobjecten::class, $besluiten->besluitinformatieobjecten());
    }

    public function test_besluiten_index_returns_collection(): void
    {
        Http::fake([
            self::BASE.'besluiten' => Http::response([
                'count' => 1,
                'next' => null,
                'previous' => null,
                'results' => [
                    ['uuid' => 'bes-1', 'identificatie' => 'BESLUIT-001'],
                ],
            ]),
        ]);

        $besluiten = Zgw::connection('main')->besluiten()->besluiten()->index();

        $this->assertCount(1, $besluiten);
        $this->assertSame('bes-1', $besluiten->first()['uuid']);
    }

    public function test_audittrail_returns_collection(): void
    {
        $uuid = 'bes-uuid';

        Http::fake([
            self::BASE.'besluiten/'.$uuid.'/audittrail' => Http::response([
                ['uuid' => 'audit-1', 'actie' => 'create'],
                ['uuid' => 'audit-2', 'actie' => 'update'],
            ]),
        ]);

        $trail = Zgw::connection('main')->besluiten()->besluiten()->audittrail($uuid);

        $this->assertCount(2, $trail);
        $this->assertSame('audit-1', $trail->first()['uuid']);
    }
}
