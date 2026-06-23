<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class CacheIsolationTest extends TestCase
{
    private const BASE = 'https://zaken.example.com/zaken/api/v1/';

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // A second connection to the same host but with a different credential (client_id).
        $app['config']->set('zgw.connections.other', [
            'urls' => [
                'zaken' => 'https://zaken.example.com/',
            ],
            'client_id' => 'other-client',
            'client_secret' => 'other-secret-with-sufficient-entropy-0123456789',
        ]);
    }

    public function test_connections_with_different_credentials_do_not_share_cache(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['uuid' => 'z-1']],
            ]),
        ]);

        Zgw::connection('main')->zaken()->zaken()->cache(120)->index();
        Zgw::connection('other')->zaken()->zaken()->cache(120)->index();

        // Each credential resolves to its own cache key, so both hit the network.
        Http::assertSentCount(2);
    }

    public function test_same_credential_shares_cache_across_calls(): void
    {
        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['uuid' => 'z-1']],
            ]),
        ]);

        Zgw::connection('main')->zaken()->zaken()->cache(120)->index();
        Zgw::connection('main')->zaken()->zaken()->cache(120)->index();

        Http::assertSentCount(1);
    }

    public function test_caching_works_through_a_configured_store(): void
    {
        config()->set('zgw.connections.main.cache_store', 'array');

        Http::fake([
            self::BASE.'zaken' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['uuid' => 'z-1']],
            ]),
        ]);

        Zgw::connection('main')->zaken()->zaken()->cache(120)->index();
        Zgw::connection('main')->zaken()->zaken()->cache(120)->index();

        Http::assertSentCount(1);
    }
}
