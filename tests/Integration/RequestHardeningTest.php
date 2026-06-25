<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\PaginationLimitException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class RequestHardeningTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    public function test_connection_exposes_configured_timeouts(): void
    {
        config()->set('zgw.connections.main.connect_timeout', 7);
        config()->set('zgw.connections.main.timeout', 21);

        $connection = Zgw::connection('main');

        $this->assertSame(7, $connection->getConnectTimeout());
        $this->assertSame(21, $connection->getTimeout());
    }

    public function test_timeouts_fall_back_to_defaults(): void
    {
        $connection = Zgw::connection('main');

        $this->assertSame(10, $connection->getConnectTimeout());
        $this->assertSame(30, $connection->getTimeout());
    }

    public function test_request_still_carries_authorization_header(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ]),
        ]);

        Zgw::connection('main')->zaken()->zaken()->index()->all();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization')
            && str_starts_with($request->header('Authorization')[0], 'Bearer '));
    }

    public function test_pagination_stops_at_configured_max_pages(): void
    {
        // Every page points to a (trusted) next page, simulating a hostile endless chain.
        Http::fake([
            self::BASE.'*' => Http::response([
                'count' => 9999,
                'next' => self::BASE.'zaken?page=next',
                'previous' => null,
                'results' => [
                    ['uuid' => 'x', 'identificatie' => 'ZAAK'],
                ],
            ]),
        ]);

        config()->set('zgw.connections.main.max_pages', 5);

        $this->expectException(PaginationLimitException::class);

        Zgw::connection('main')->zaken()->zaken()->index()->all();
    }

    public function test_max_pages_falls_back_to_default(): void
    {
        $this->assertSame(1000, Zgw::connection('main')->getMaxPages());
    }

    public function test_retries_are_disabled_by_default(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::sequence()
                ->push('', 503)
                ->push($this->emptyPage(), 200),
        ]);

        $this->expectException(ApiRequestException::class);

        try {
            Zgw::connection('main')->zaken()->zaken()->index()->all();
        } finally {
            // Without retries the first 503 is final: only one request is ever sent.
            Http::assertSentCount(1);
        }
    }

    public function test_idempotent_request_is_retried_on_transient_failure(): void
    {
        Sleep::fake();

        config()->set('zgw.connections.main.retry_times', 2);
        config()->set('zgw.connections.main.retry_sleep_ms', 10);

        Http::fake([
            self::BASE.'zaken' => Http::sequence()
                ->push('', 503)
                ->push('', 429)
                ->push($this->emptyPage(), 200),
        ]);

        $result = Zgw::connection('main')->zaken()->zaken()->index()->all();

        $this->assertSame([], $result);
        Http::assertSentCount(3);
    }

    public function test_write_request_is_not_retried(): void
    {
        Sleep::fake();

        config()->set('zgw.connections.main.retry_times', 3);
        config()->set('zgw.connections.main.retry_sleep_ms', 10);

        Http::fake([
            self::BASE.'zaken' => Http::sequence()
                ->push('', 503)
                ->push(['url' => self::BASE.'zaken/1'], 201),
        ]);

        $this->expectException(ApiRequestException::class);

        try {
            Zgw::connection('main')->zaken()->zaken()->store(['identificatie' => 'ZAAK-1']);
        } finally {
            // POST is not idempotent, so the 503 is final: the write is never repeated.
            Http::assertSentCount(1);
        }
    }

    public function test_retry_gives_up_and_surfaces_the_final_failure(): void
    {
        Sleep::fake();

        config()->set('zgw.connections.main.retry_times', 2);
        config()->set('zgw.connections.main.retry_sleep_ms', 10);

        Http::fake([
            self::BASE.'zaken' => Http::response('', 503),
        ]);

        $this->expectException(ApiRequestException::class);

        try {
            Zgw::connection('main')->zaken()->zaken()->index()->all();
        } finally {
            // Initial attempt plus two retries.
            Http::assertSentCount(3);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPage(): array
    {
        return [
            'count' => 0,
            'next' => null,
            'previous' => null,
            'results' => [],
        ];
    }
}
