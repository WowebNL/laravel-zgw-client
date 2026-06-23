<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Api\Endpoints\Zaken\Resultaten;
use Woweb\Zgw\Api\Endpoints\Zaken\Rollen;
use Woweb\Zgw\Api\Endpoints\Zaken\Statussen;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakinformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakobjecten;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaken;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class ZakenApiTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    public function test_endpoint_accessors_return_correct_instances(): void
    {
        $zaken = Zgw::connection('main')->zaken();

        $this->assertInstanceOf(Zaken::class, $zaken->zaken());
        $this->assertInstanceOf(Statussen::class, $zaken->statussen());
        $this->assertInstanceOf(Rollen::class, $zaken->rollen());
        $this->assertInstanceOf(Resultaten::class, $zaken->resultaten());
        $this->assertInstanceOf(Zaakinformatieobjecten::class, $zaken->zaakinformatieobjecten());
        $this->assertInstanceOf(Zaakobjecten::class, $zaken->zaakobjecten());
    }

    public function test_index_returns_collection(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 2,
                'next' => null,
                'previous' => null,
                'results' => [
                    ['uuid' => 'abc-1', 'identificatie' => 'ZAAK-001'],
                    ['uuid' => 'abc-2', 'identificatie' => 'ZAAK-002'],
                ],
            ]),
        ]);

        $zaken = Zgw::connection('main')->zaken()->zaken()->index();

        $this->assertCount(2, $zaken);
        $this->assertSame('abc-1', $zaken->first()['uuid']);
    }

    public function test_get_all_follows_pagination(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 3,
                'next' => self::BASE.'zaken?page=2',
                'previous' => null,
                'results' => [
                    ['uuid' => 'p1-1', 'identificatie' => 'ZAAK-001'],
                ],
            ]),
            self::BASE.'zaken?page=2' => Http::response([
                'count' => 3,
                'next' => null,
                'previous' => self::BASE.'zaken',
                'results' => [
                    ['uuid' => 'p2-1', 'identificatie' => 'ZAAK-002'],
                    ['uuid' => 'p2-2', 'identificatie' => 'ZAAK-003'],
                ],
            ]),
        ]);

        $zaken = Zgw::connection('main')->zaken()->zaken()->index();

        $this->assertCount(3, $zaken);
    }

    public function test_show_returns_array(): void
    {
        $uuid = 'abc-123';

        Http::fake([
            self::BASE.'zaken/'.$uuid => Http::response([
                'uuid' => $uuid,
                'identificatie' => 'ZAAK-001',
            ]),
        ]);

        $zaak = Zgw::connection('main')->zaken()->zaken()->show($uuid);

        $this->assertSame($uuid, $zaak['uuid']);
    }

    public function test_store_sends_post_and_returns_array(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'uuid' => 'new-uuid',
                'identificatie' => 'ZAAK-NEW',
            ], 201),
        ]);

        $zaak = Zgw::connection('main')->zaken()->zaken()->store([
            'bronorganisatie' => '123456789',
            'zaaktype' => 'https://catalogi.example.com/api/v1/zaaktypen/type-uuid',
            'startdatum' => '2024-01-01',
        ]);

        $this->assertSame('new-uuid', $zaak['uuid']);

        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_patch_sends_patch_request(): void
    {
        $uuid = 'patch-uuid';

        Http::fake([
            self::BASE.'zaken/'.$uuid => Http::response(['uuid' => $uuid]),
        ]);

        $zaak = Zgw::connection('main')->zaken()->zaken()->patch($uuid, ['omschrijving' => 'Updated']);

        $this->assertSame($uuid, $zaak['uuid']);
    }

    public function test_delete_returns_true_on_204(): void
    {
        $uuid = 'del-uuid';

        Http::fake([
            self::BASE.'zaken/'.$uuid => Http::response(null, 204),
        ]);

        $result = Zgw::connection('main')->zaken()->zaken()->delete($uuid);

        $this->assertTrue($result);
    }

    public function test_failed_request_throws_api_request_exception(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response(['detail' => 'Not found'], 404),
        ]);

        $this->expectException(ApiRequestException::class);

        Zgw::connection('main')->zaken()->zaken()->index();
    }

    public function test_delete_failure_throws_with_response(): void
    {
        $uuid = 'del-uuid';

        Http::fake([
            self::BASE.'zaken/'.$uuid => Http::response(['detail' => 'Not found'], 404),
        ]);

        try {
            Zgw::connection('main')->zaken()->zaken()->delete($uuid);
            $this->fail('Expected ApiRequestException was not thrown.');
        } catch (ApiRequestException $e) {
            $this->assertSame(404, $e->getResponse()->status());
        }
    }

    public function test_exception_message_excludes_body_but_response_retains_it(): void
    {
        $secret = 'burgerservicenummer-123456789';

        Http::fake([
            self::BASE.'zaken' => Http::response(['detail' => $secret], 422),
        ]);

        try {
            Zgw::connection('main')->zaken()->zaken()->index();
            $this->fail('Expected ApiRequestException was not thrown.');
        } catch (ApiRequestException $e) {
            // The status code is in the message, but the (potentially PII-bearing) body is not.
            $this->assertStringContainsString('422', $e->getMessage());
            $this->assertStringNotContainsString($secret, $e->getMessage());

            // The body remains available for deliberate inspection via the response.
            $this->assertStringContainsString($secret, $e->getResponse()->body());
        }
    }

    public function test_zaakeigenschappen_nested_resource(): void
    {
        $zaakUuid = 'zaak-uuid';

        Http::fake([
            self::BASE.'zaken/'.$zaakUuid.'/zaakeigenschappen' => Http::response([
                'count' => 1,
                'next' => null,
                'previous' => null,
                'results' => [
                    ['uuid' => 'eig-1', 'naam' => 'Eigenschap1'],
                ],
            ]),
        ]);

        $eigenschappen = Zgw::connection('main')
            ->zaken()
            ->zaken()
            ->zaakeigenschappen($zaakUuid)
            ->index();

        $this->assertCount(1, $eigenschappen);
        $this->assertSame('eig-1', $eigenschappen->first()['uuid']);
    }
}
