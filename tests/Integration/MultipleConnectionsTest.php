<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;
use Woweb\Zgw\ZgwManager;

class MultipleConnectionsTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('zgw.connections.secondary', [
            'urls' => [
                'zaken' => 'https://zaken2.example.com/zaken/api/v1/',
                'catalogi' => 'https://catalogi2.example.com/catalogi/api/v1/',
                'documenten' => 'https://documenten2.example.com/documenten/api/v1/',
                'besluiten' => 'https://besluiten2.example.com/besluiten/api/v1/',
            ],
            'client_id' => 'secondary-client',
            'client_secret' => 'secondary-secret-with-sufficient-entropy-0123',
            'user_id' => 'secondary-user',
            'user_representation' => 'Secondary User',
            'accept_crs' => 'EPSG:4326',
            'content_crs' => 'EPSG:4326',
        ]);
    }

    public function test_different_connections_use_different_base_urls(): void
    {
        Http::fake([
            'https://zaken.example.com/zaken/api/v1/zaken' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['uuid' => 'main-zaak']],
            ]),
            'https://zaken2.example.com/zaken/api/v1/zaken' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['uuid' => 'secondary-zaak']],
            ]),
        ]);

        $mainZaken = Zgw::connection('main')->zaken()->zaken()->index();
        $secondaryZaken = Zgw::connection('secondary')->zaken()->zaken()->index();

        $this->assertSame('main-zaak', $mainZaken->first()['uuid']);
        $this->assertSame('secondary-zaak', $secondaryZaken->first()['uuid']);
    }

    public function test_connection_instances_are_cached(): void
    {
        $manager = app(ZgwManager::class);

        $a = $manager->connection('main');
        $b = $manager->connection('main');

        $this->assertSame($a, $b);
    }
}
