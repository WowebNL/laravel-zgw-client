<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class LazyPaginationTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    public function test_index_returns_a_lazy_collection(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['uuid' => 'z-1']],
            ]),
        ]);

        $result = Zgw::connection('main')->zaken()->zaken()->index();

        $this->assertInstanceOf(LazyCollection::class, $result);
    }

    public function test_iterating_drives_pagination_lazily(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 3,
                'next' => self::BASE.'zaken?page=2',
                'previous' => null,
                'results' => [['uuid' => 'p1']],
            ]),
            self::BASE.'zaken?page=2' => Http::response([
                'count' => 3, 'next' => null, 'previous' => self::BASE.'zaken',
                'results' => [['uuid' => 'p2'], ['uuid' => 'p3']],
            ]),
        ]);

        $lazy = Zgw::connection('main')->zaken()->zaken()->index();

        // Nothing is fetched until the collection is iterated.
        Http::assertNothingSent();

        $uuids = $lazy->pluck('uuid')->all();

        $this->assertSame(['p1', 'p2', 'p3'], $uuids);
        Http::assertSentCount(2);
    }

    public function test_take_stops_before_fetching_further_pages(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 99,
                'next' => self::BASE.'zaken?page=2',
                'previous' => null,
                'results' => [['uuid' => 'p1'], ['uuid' => 'p2']],
            ]),
            self::BASE.'zaken?page=2' => Http::response([
                'count' => 99, 'next' => null, 'previous' => null,
                'results' => [['uuid' => 'p3']],
            ]),
        ]);

        // Taking the first item only needs the first page; the second page is never requested.
        $first = Zgw::connection('main')->zaken()->zaken()->index()->take(1)->all();

        $this->assertSame(['p1'], array_column($first, 'uuid'));
        Http::assertSentCount(1);
    }

    public function test_index_reads_a_bare_array_from_a_non_paginated_endpoint(): void
    {
        // zaakinformatieobjecten is not paginated: the ZGW Zaken API returns a bare
        // JSON array, not a {count,next,previous,results} envelope.
        Http::fake([
            self::BASE.'zaakinformatieobjecten*' => Http::response([
                ['url' => self::BASE.'zaakinformatieobjecten/a', 'informatieobject' => 'https://documenten.example.com/io/a'],
                ['url' => self::BASE.'zaakinformatieobjecten/b', 'informatieobject' => 'https://documenten.example.com/io/b'],
            ]),
        ]);

        $items = Zgw::connection('main')->zaken()->zaakinformatieobjecten()
            ->index(['zaak' => self::BASE.'zaken/1'])->all();

        $this->assertCount(2, $items);
        $this->assertSame('https://documenten.example.com/io/a', $items[0]['informatieobject']);
        // uuid is still derived from the url segment, as for paginated items.
        $this->assertSame('a', $items[0]['uuid']);
    }

    public function test_index_returns_empty_for_an_empty_bare_array(): void
    {
        Http::fake([
            self::BASE.'zaakinformatieobjecten*' => Http::response([]),
        ]);

        $items = Zgw::connection('main')->zaken()->zaakinformatieobjecten()
            ->index(['zaak' => self::BASE.'zaken/1'])->all();

        $this->assertSame([], $items);
    }
}
