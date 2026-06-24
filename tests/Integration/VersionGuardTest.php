<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Exceptions\UnsupportedOperationException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class VersionGuardTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // A connection that targets the older ZGW 1.5 release.
        $app['config']->set('zgw.connections.legacy', [
            'urls' => [
                'zaken' => 'https://zaken.example.com/zaken/api/v1/',
                'catalogi' => 'https://catalogi.example.com/catalogi/api/v1/',
            ],
            'version' => '1.5',
            'client_id' => 'legacy-client',
            'client_secret' => 'legacy-secret-with-sufficient-entropy-01234567',
        ]);
    }

    public function test_zaaknotities_is_rejected_on_a_1_5_connection(): void
    {
        Http::fake();

        $this->expectException(UnsupportedOperationException::class);
        $this->expectExceptionMessage('ZGW 1.5');

        // index() is lazy but the version guard runs eagerly, so this throws without iterating.
        Zgw::connection('legacy')->zaken()->zaaknotities()->index();
    }

    public function test_catalogus_update_is_rejected_on_a_1_5_connection(): void
    {
        Http::fake();

        $this->expectException(UnsupportedOperationException::class);

        Zgw::connection('legacy')->catalogi()->catalogussen()->patch('a1b2c3d4-0000-4000-8000-000000000000', ['foo' => 'bar']);
    }

    public function test_reserveer_zaaknummer_is_rejected_on_a_1_5_connection(): void
    {
        Http::fake();

        $this->expectException(UnsupportedOperationException::class);

        Zgw::connection('legacy')->zaken()->zaken()->reserveerZaaknummer();
    }

    public function test_no_request_is_sent_when_the_operation_is_unsupported(): void
    {
        Http::fake();

        try {
            Zgw::connection('legacy')->zaken()->zaaknotities()->store(['foo' => 'bar']);
        } catch (UnsupportedOperationException) {
            // expected
        }

        Http::assertNothingSent();
    }

    public function test_supported_operation_on_1_5_connection_still_works(): void
    {
        Http::fake([
            'https://catalogi.example.com/catalogi/api/v1/catalogussen' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['url' => 'https://catalogi.example.com/catalogi/api/v1/catalogussen/c-1']],
            ]),
        ]);

        $catalogussen = Zgw::connection('legacy')->catalogi()->catalogussen()->index();

        $this->assertCount(1, $catalogussen);
    }

    public function test_zaaknotities_works_on_a_1_7_connection(): void
    {
        Http::fake([
            'https://zaken.example.com/zaken/api/v1/zaaknotities' => Http::response([
                'count' => 1, 'next' => null, 'previous' => null,
                'results' => [['url' => 'https://zaken.example.com/zaken/api/v1/zaaknotities/n-1']],
            ]),
        ]);

        // The default 'main' connection targets ZGW 1.7.
        $notities = Zgw::connection('main')->zaken()->zaaknotities()->index();

        $this->assertCount(1, $notities);
    }
}
