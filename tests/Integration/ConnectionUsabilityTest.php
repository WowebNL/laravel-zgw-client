<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Illuminate\Support\Facades\Http;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Exceptions\WeakSecretException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

/**
 * The manager can validate connection configuration up front, and a connection can run a light
 * read smoke-test to confirm it works end to end.
 */
class ConnectionUsabilityTest extends TestCase
{
    private const CATALOGUSSEN = 'https://catalogi.example.com/catalogi/api/v1/catalogussen*';

    public function test_validate_rejects_a_weak_secret_before_any_request(): void
    {
        Http::preventStrayRequests();

        config()->set('zgw.connections.weak', [
            'urls' => ['catalogi' => 'https://catalogi.example.com/catalogi/api/v1/'],
            'client_id' => 'weak-client',
            'client_secret' => 'short',
        ]);

        $this->expectException(WeakSecretException::class);

        Zgw::validate('weak');
    }

    public function test_validate_all_names_the_failing_connection(): void
    {
        Http::preventStrayRequests();

        config()->set('zgw.connections.gemeente_b', [
            'urls' => ['catalogi' => 'https://catalogi.example.com/catalogi/api/v1/'],
            'client_id' => 'b-client',
            'client_secret' => 'too-short',
        ]);

        try {
            Zgw::validateAll();
            $this->fail('Expected a WeakSecretException for the weak connection.');
        } catch (WeakSecretException $e) {
            $this->assertStringContainsString('gemeente_b', $e->getMessage());
        }
    }

    public function test_validate_all_passes_when_every_secret_is_strong(): void
    {
        Http::preventStrayRequests();

        // Only the strong default 'main' connection is configured.
        Zgw::validateAll();

        $this->assertInstanceOf(ZgwConnection::class, Zgw::connection('main'));
    }

    public function test_is_usable_is_true_on_a_successful_read(): void
    {
        Http::fake([
            self::CATALOGUSSEN => Http::response([
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ]),
        ]);

        $this->assertTrue(Zgw::connection('main')->isUsable());
    }

    public function test_assert_usable_fails_on_a_rejected_credential(): void
    {
        Http::fake([
            self::CATALOGUSSEN => Http::response(['detail' => 'Invalid credentials'], 401),
        ]);

        $this->assertFalse(Zgw::connection('main')->isUsable());
    }
}
