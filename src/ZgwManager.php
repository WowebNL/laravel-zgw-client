<?php

declare(strict_types=1);

namespace Woweb\Zgw;

use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Contracts\AuthorizationInterface;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;
use Woweb\Zgw\Exceptions\WeakSecretException;

class ZgwManager
{
    /** @var array<string, ZgwConnection> */
    private array $connections = [];

    public function __construct(
        private readonly AuthorizationInterface $authorization,
    ) {}

    /**
     * Get a named connection. Falls back to the configured default when null.
     */
    public function connection(?string $name = null): ZgwConnection
    {
        $name ??= config('zgw.default', 'main');

        if (! isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * Validate a connection's configuration (secret strength and that it is defined) without making
     * any API call. Building a connection runs the same secret check the first use would, so calling
     * this up front surfaces a weak or misconfigured secret at boot or deploy rather than lazily on
     * the first request.
     *
     * @throws InvalidConfigurationException when the connection is not defined.
     * @throws WeakSecretException when the secret fails the connection's secret_rules.
     */
    public function validate(?string $name = null): void
    {
        $name ??= config('zgw.default', 'main');

        try {
            $this->makeConnection($name);
        } catch (WeakSecretException $e) {
            throw new WeakSecretException("ZGW connection [{$name}]: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate every configured connection. With many connections (a client_id/secret per
     * municipality) this catches a bad credential for any of them at deploy time, not on first use.
     * Throws on the first connection whose secret fails, naming it.
     *
     * @throws WeakSecretException for the first connection whose secret fails its rules.
     */
    public function validateAll(): void
    {
        /** @var array<string, mixed>|null $connections */
        $connections = config('zgw.connections');

        foreach (array_keys($connections ?? []) as $name) {
            $this->validate((string) $name);
        }
    }

    private function makeConnection(string $name): ZgwConnection
    {
        /** @var array<string, array<string, string>>|null $connections */
        $connections = config('zgw.connections');

        if (! isset($connections[$name])) {
            throw new InvalidConfigurationException(
                "ZGW connection [{$name}] is not defined in config/zgw.php."
            );
        }

        return new ZgwConnection($connections[$name], $this->authorization, $name);
    }
}
