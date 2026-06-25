<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Events\ZgwRequestSent;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class RequestEventTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    public function test_dispatches_request_sent_event_with_connection_context(): void
    {
        Event::fake([ZgwRequestSent::class]);

        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ]),
        ]);

        Zgw::connection('main')->zaken()->zaken()->index()->all();

        Event::assertDispatched(ZgwRequestSent::class, function (ZgwRequestSent $event): bool {
            return $event->connection === 'main'
                && $event->clientId === 'test-client'
                && $event->method === 'GET'
                && $event->status === 200
                && str_contains($event->uri, '/zaken');
        });
    }
}
