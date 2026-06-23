<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api;

use Woweb\Zgw\Api\Endpoints\Besluiten\Besluiten;
use Woweb\Zgw\Api\Endpoints\Besluiten\Besluitinformatieobjecten;
use Woweb\Zgw\Connection\ZgwConnection;

class BesluitenApi
{
    public function __construct(private readonly ZgwConnection $connection) {}

    public function besluiten(): Besluiten
    {
        return new Besluiten($this->connection);
    }

    public function besluitinformatieobjecten(): Besluitinformatieobjecten
    {
        return new Besluitinformatieobjecten($this->connection);
    }
}
