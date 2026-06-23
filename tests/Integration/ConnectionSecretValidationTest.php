<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Integration;

use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Exceptions\WeakSecretException;
use Woweb\Zgw\Facades\Zgw;
use Woweb\Zgw\Tests\TestCase;

class ConnectionSecretValidationTest extends TestCase
{
    public function test_connection_with_weak_secret_is_rejected_on_build(): void
    {
        config()->set('zgw.connections.weak', [
            'urls' => ['zaken' => 'https://zaken.example.com/'],
            'client_id' => 'weak-client',
            'client_secret' => 'short',
        ]);

        $this->expectException(WeakSecretException::class);

        Zgw::connection('weak');
    }

    public function test_per_connection_rules_can_relax_the_minimum(): void
    {
        config()->set('zgw.connections.legacy', [
            'urls' => ['zaken' => 'https://zaken.example.com/'],
            'client_id' => 'legacy-client',
            'client_secret' => 'short-legacy-secret',
            'secret_rules' => ['min_length' => 8],
        ]);

        $connection = Zgw::connection('legacy');

        $this->assertInstanceOf(ZgwConnection::class, $connection);
    }

    public function test_per_connection_rules_can_tighten_requirements(): void
    {
        config()->set('zgw.connections.strict', [
            'urls' => ['zaken' => 'https://zaken.example.com/'],
            'client_id' => 'strict-client',
            // 32+ chars but no symbol, which the tightened rule requires.
            'client_secret' => 'abcdefghijklmnopqrstuvwxyzabcdef',
            'secret_rules' => ['require_symbol' => true],
        ]);

        $this->expectException(WeakSecretException::class);

        Zgw::connection('strict');
    }
}
