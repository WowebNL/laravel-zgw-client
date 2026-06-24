<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Woweb\Zgw\ZgwServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ZgwServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('zgw.default', 'main');
        $app['config']->set('zgw.connections.main', [
            'urls' => [
                'zaken' => 'https://zaken.example.com/zaken/api/v1/',
                'catalogi' => 'https://catalogi.example.com/catalogi/api/v1/',
                'documenten' => 'https://documenten.example.com/documenten/api/v1/',
                'besluiten' => 'https://besluiten.example.com/besluiten/api/v1/',
                'autorisaties' => 'https://autorisaties.example.com/autorisaties/api/v1/',
                'notificaties' => 'https://notificaties.example.com/notificaties/api/v1/',
            ],
            'version' => '1.7',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret-with-sufficient-entropy-0123456789',
            'user_id' => 'test-user',
            'user_representation' => 'Test User',
            'accept_crs' => 'EPSG:4326',
            'content_crs' => 'EPSG:4326',
        ]);
    }
}
