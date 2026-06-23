<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class EndpointCacheTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    public function test_cached_index_only_sends_one_request(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 1,
                'next' => null,
                'previous' => null,
                'results' => [
                    ['uuid' => 'abc-1', 'identificatie' => 'ZAAK-001'],
                ],
            ]),
        ]);

        $first = Zgw::connection('main')->zaken()->zaken()->cache(120)->index();
        $second = Zgw::connection('main')->zaken()->zaken()->cache(120)->index();

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        Http::assertSentCount(1);
    }

    public function test_cached_show_only_sends_one_request(): void
    {
        $uuid = 'abc-123';

        Http::fake([
            self::BASE.'zaken/'.$uuid => Http::response([
                'uuid' => $uuid,
                'identificatie' => 'ZAAK-001',
            ]),
        ]);

        $first = Zgw::connection('main')->zaken()->zaken()->cache()->show($uuid);
        $second = Zgw::connection('main')->zaken()->zaken()->cache()->show($uuid);

        $this->assertSame($uuid, $first['uuid']);
        $this->assertSame($uuid, $second['uuid']);
        Http::assertSentCount(1);
    }

    public function test_uuid_is_extracted_from_url_when_missing(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 1,
                'next' => null,
                'previous' => null,
                'results' => [
                    ['url' => self::BASE.'zaken/extracted-uuid', 'identificatie' => 'ZAAK-001'],
                ],
            ]),
        ]);

        $zaken = Zgw::connection('main')->zaken()->zaken()->index();

        $this->assertSame('extracted-uuid', $zaken->first()['uuid']);
    }
}
