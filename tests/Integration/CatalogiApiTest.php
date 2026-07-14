<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Api\Endpoints\Catalogi\Catalogussen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Eigenschappen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Informatieobjecttypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Resultaattypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Roltypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Statustypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Zaaktypen;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class CatalogiApiTest extends TestCase
{
    private const BASE = 'https://catalogi.example.com/catalogi/api/v1/';

    public function test_endpoint_accessors_return_correct_instances(): void
    {
        $catalogi = Zgw::connection('main')->catalogi();

        $this->assertInstanceOf(Catalogussen::class, $catalogi->catalogussen());
        $this->assertInstanceOf(Zaaktypen::class, $catalogi->zaaktypen());
        $this->assertInstanceOf(Informatieobjecttypen::class, $catalogi->informatieobjecttypen());
        $this->assertInstanceOf(Roltypen::class, $catalogi->roltypen());
        $this->assertInstanceOf(Statustypen::class, $catalogi->statustypen());
        $this->assertInstanceOf(Resultaattypen::class, $catalogi->resultaattypen());
        $this->assertInstanceOf(Eigenschappen::class, $catalogi->eigenschappen());
    }

    public function test_zaaktypen_index_returns_collection(): void
    {
        Http::fake([
            self::BASE.'zaaktypen' => Http::response([
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [
                    ['uuid' => 'zt-1', 'omschrijving' => 'Type 1'],
                    ['uuid' => 'zt-2', 'omschrijving' => 'Type 2'],
                ],
            ]),
        ]);

        $zaaktypen = Zgw::connection('main')->catalogi()->zaaktypen()->index();

        $this->assertCount(2, $zaaktypen);
        $this->assertSame('zt-1', $zaaktypen->first()['uuid']);
    }

    public function test_publish_sends_no_request_body(): void
    {
        $uuid = 'zt-1';

        Http::fake([
            self::BASE.'zaaktypen/'.$uuid.'/publish' => Http::response([
                'uuid' => $uuid,
                'concept' => false,
            ]),
        ]);

        $zaaktype = Zgw::connection('main')->catalogi()->zaaktypen()->publish($uuid);

        $this->assertFalse($zaaktype['concept']);

        // The spec defines no request body for the publish operations, but does require a
        // Content-Type: application/json header. A JSON list body ("[]") makes Open Zaak
        // respond 400 "Verwacht een dictionary, kreeg een list".
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->body() === ''
            && $request->hasHeader('Content-Type', 'application/json'));
    }
}
