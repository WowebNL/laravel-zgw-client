<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit\Connection;

use Woweb\Zgw\Api\BesluitenApi;
use Woweb\Zgw\Api\CatalogiApi;
use Woweb\Zgw\Api\DocumentenApi;
use Woweb\Zgw\Api\ZakenApi;
use Woweb\Zgw\Connection\ZgwConnection;
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

    public function test_get_base_url_appends_api_path(): void
    {
        $url = $this->connection->getBaseUrl('zaken');

        $this->assertSame('https://zaken.example.com/zaken/api/v1/', $url);
    }

    public function test_get_base_url_strips_trailing_slash_before_appending(): void
    {
        // The config value already has a trailing slash; ensure no double-slash in the path
        $url = $this->connection->getBaseUrl('zaken');
        $path = parse_url($url, PHP_URL_PATH);

        $this->assertStringNotContainsString('//', (string) $path);
    }

    public function test_get_base_url_throws_when_url_not_configured(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('urls.unknown_api');

        $this->connection->getBaseUrl('unknown_api');
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
    }
}
