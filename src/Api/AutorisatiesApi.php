<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api;

use Woweb\Zgw\Api\Endpoints\Autorisaties\Applicaties;
use Woweb\Zgw\Connection\ZgwConnection;

class AutorisatiesApi
{
    public function __construct(private readonly ZgwConnection $connection) {}

    public function applicaties(): Applicaties
    {
        return new Applicaties($this->connection);
    }
}
