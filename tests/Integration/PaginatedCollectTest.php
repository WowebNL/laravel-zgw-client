<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

/**
 * Realising a paginated collection eagerly (all(), collect(), the cache) must return every item of
 * every page, in order, without dropping or duplicating items.
 */
class PaginatedCollectTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    /**
     * @return array<string, array{int}>
     */
    public static function pageCounts(): array
    {
        return [
            'two pages' => [2],
            'three pages' => [3],
        ];
    }

    #[DataProvider('pageCounts')]
    public function test_all_returns_every_item_of_every_page(int $pages): void
    {
        $expected = $this->fakePages('zaken', $pages);

        $all = Zgw::connection('main')->zaken()->zaken()->index()->all();

        $this->assertTrue(array_is_list($all));
        $this->assertSame($expected, array_column($all, 'uuid'));
        Http::assertSentCount($pages);
    }

    #[DataProvider('pageCounts')]
    public function test_collect_returns_every_item_of_every_page(int $pages): void
    {
        $expected = $this->fakePages('zaken', $pages);

        $collection = Zgw::connection('main')->zaken()->zaken()->index()->collect();

        $this->assertCount(count($expected), $collection);
        $this->assertSame($expected, $collection->pluck('uuid')->all());
        $this->assertTrue(array_is_list($collection->all()));
    }

    #[DataProvider('pageCounts')]
    public function test_cached_index_stores_and_returns_every_item_of_every_page(int $pages): void
    {
        $expected = $this->fakePages('zaken', $pages);

        $first = Zgw::connection('main')->zaken()->zaken()->cache(120)->index()->all();
        $second = Zgw::connection('main')->zaken()->zaken()->cache(120)->index()->all();

        $this->assertSame($expected, array_column($first, 'uuid'));
        $this->assertSame($expected, array_column($second, 'uuid'));
        // The second call is served from the cache, so only the first call hits the API.
        Http::assertSentCount($pages);
    }

    #[DataProvider('pageCounts')]
    public function test_search_all_returns_every_item_of_every_page(int $pages): void
    {
        $expected = $this->fakePages('zaken/_zoek', $pages);

        $all = Zgw::connection('main')->zaken()->zaken()->zoek(['identificatie' => 'X'])->all();

        $this->assertTrue(array_is_list($all));
        $this->assertSame($expected, array_column($all, 'uuid'));
    }

    #[DataProvider('pageCounts')]
    public function test_iterating_yields_every_item_once_in_order(int $pages): void
    {
        $expected = $this->fakePages('zaken', $pages);

        $seen = [];
        foreach (Zgw::connection('main')->zaken()->zaken()->index() as $item) {
            $seen[] = $item['uuid'];
        }

        $this->assertSame($expected, $seen);
    }

    /**
     * Fake a paginated list endpoint with the given number of pages of two items each, linked by
     * "next" URLs, and return the uuids in the order the pages hold them.
     *
     * @return list<string>
     */
    private function fakePages(string $path, int $pages): array
    {
        $responses = [];
        $expected = [];

        for ($page = 1; $page <= $pages; $page++) {
            $url = self::BASE.$path.($page === 1 ? '' : '?page='.$page);
            $next = $page < $pages ? self::BASE.$path.'?page='.($page + 1) : null;

            $results = [];
            foreach ([1, 2] as $position) {
                $uuid = sprintf('item-%d-%d', $page, $position);
                $results[] = ['uuid' => $uuid];
                $expected[] = $uuid;
            }

            $responses[$url] = Http::response([
                'count' => $pages * 2,
                'next' => $next,
                'previous' => null,
                'results' => $results,
            ]);
        }

        Http::fake($responses);

        return $expected;
    }
}
