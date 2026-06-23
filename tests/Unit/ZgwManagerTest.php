<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Unit;

use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;
use Woweb\Zgw\ZgwManager;

class ZgwManagerTest extends TestCase
{
    public function test_it_resolves_the_default_connection(): void
    {
        $connection = app(ZgwManager::class)->connection();

        $this->assertInstanceOf(ZgwConnection::class, $connection);
    }

    public function test_it_resolves_a_named_connection(): void
    {
        $connection = app(ZgwManager::class)->connection('main');

        $this->assertInstanceOf(ZgwConnection::class, $connection);
    }

    public function test_it_returns_the_same_instance_for_the_same_connection_name(): void
    {
        $manager = app(ZgwManager::class);

        $this->assertSame($manager->connection('main'), $manager->connection('main'));
    }

    public function test_it_throws_for_an_unknown_connection(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        app(ZgwManager::class)->connection('does-not-exist');
    }

    public function test_facade_resolves_connection(): void
    {
        $this->assertInstanceOf(ZgwConnection::class, Zgw::connection());
    }
}
