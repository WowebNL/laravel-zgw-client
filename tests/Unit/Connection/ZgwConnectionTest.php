<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Connection;

use Woweb\Zgw\Api\AutorisatiesApi;
use Woweb\Zgw\Api\BesluitenApi;
use Woweb\Zgw\Api\CatalogiApi;
use Woweb\Zgw\Api\DocumentenApi;
use Woweb\Zgw\Api\NotificatiesApi;
use Woweb\Zgw\Api\ZakenApi;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Enums\ZgwVersion;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;
use Woweb\Zgw\Tests\TestCase;
use Woweb\Zgw\ZgwManager;

class ZgwConnectionTest extends TestCase
{
    private ZgwConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = app(ZgwManager::class)->connection('main');
    }

    public function test_get_base_url_returns_configured_full_url(): void
    {
        $url = $this->connection->getBaseUrl('zaken');

        $this->assertSame('https://zaken.example.com/zaken/api/v1/', $url);
    }

    public function test_get_base_url_ensures_single_trailing_slash(): void
    {
        // The configured value already has a trailing slash; ensure no double slash in the path.
        $url = $this->connection->getBaseUrl('zaken');
        $path = parse_url($url, PHP_URL_PATH);

        $this->assertStringEndsWith('/', $url);
        $this->assertStringNotContainsString('//', (string) $path);
    }

    public function test_get_base_url_throws_when_url_not_configured(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('urls.unknown_api');

        $this->connection->getBaseUrl('unknown_api');
    }

    public function test_get_version_returns_configured_version(): void
    {
        $this->assertSame(ZgwVersion::V1_7, $this->connection->getVersion());
        $this->assertTrue($this->connection->getVersion()->isAtLeast(ZgwVersion::V1_6));
    }

    public function test_get_version_throws_on_unsupported_value(): void
    {
        config()->set('zgw.connections.versiontest', [
            'urls' => ['zaken' => 'https://zaken.example.com/zaken/api/v1/'],
            'client_id' => 'version-test',
            'client_secret' => 'version-test-secret-with-sufficient-entropy-0123',
            'version' => '9.9',
        ]);

        $this->expectException(InvalidConfigurationException::class);

        app(ZgwManager::class)->connection('versiontest')->getVersion();
    }

    public function test_get_headers_contains_required_keys(): void
    {
        $headers = $this->connection->getHeaders();

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertArrayHasKey('Accept-Crs', $headers);
        $this->assertArrayHasKey('Content-Crs', $headers);
        $this->assertStringStartsWith('Bearer ', $headers['Authorization']);
    }

    public function test_it_creates_api_facades(): void
    {
        $this->assertInstanceOf(ZakenApi::class, $this->connection->zaken());
        $this->assertInstanceOf(CatalogiApi::class, $this->connection->catalogi());
        $this->assertInstanceOf(DocumentenApi::class, $this->connection->documenten());
        $this->assertInstanceOf(BesluitenApi::class, $this->connection->besluiten());
        $this->assertInstanceOf(AutorisatiesApi::class, $this->connection->autorisaties());
        $this->assertInstanceOf(NotificatiesApi::class, $this->connection->notificaties());
    }
}
