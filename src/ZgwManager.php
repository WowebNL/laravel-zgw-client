<?php

declare(strict_types=1);

namespace Woweb\Zgw;

use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Contracts\AuthorizationInterface;
use Woweb\Zgw\Exceptions\InvalidConfigurationException;

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

    private function makeConnection(string $name): ZgwConnection
    {
        /** @var array<string, array<string, string>>|null $connections */
        $connections = config('zgw.connections');

        if (! isset($connections[$name])) {
            throw new InvalidConfigurationException(
                "ZGW connection [{$name}] is not defined in config/zgw.php."
            );
        }

        return new ZgwConnection($connections[$name], $this->authorization);
    }
}
