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
                'zaken' => 'https://zaken.example.com/',
                'catalogi' => 'https://catalogi.example.com/',
                'documenten' => 'https://documenten.example.com/',
                'besluiten' => 'https://besluiten.example.com/',
            ],
            'client_id' => 'test-client',
            'client_secret' => 'test-secret-with-sufficient-entropy-0123456789',
            'user_id' => 'test-user',
            'user_representation' => 'Test User',
            'accept_crs' => 'EPSG:4326',
            'content_crs' => 'EPSG:4326',
        ]);
    }
}
