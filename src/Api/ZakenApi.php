<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api;

use Woweb\Zgw\Api\Endpoints\Zaken\Resultaten;
use Woweb\Zgw\Api\Endpoints\Zaken\Rollen;
use Woweb\Zgw\Api\Endpoints\Zaken\Statussen;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakinformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaakobjecten;
use Woweb\Zgw\Api\Endpoints\Zaken\Zaken;
use Woweb\Zgw\Connection\ZgwConnection;

class ZakenApi
{
    public function __construct(private readonly ZgwConnection $connection) {}

    public function zaken(): Zaken
    {
        return new Zaken($this->connection);
    }

    public function statussen(): Statussen
    {
        return new Statussen($this->connection);
    }

    public function rollen(): Rollen
    {
        return new Rollen($this->connection);
    }

    public function resultaten(): Resultaten
    {
        return new Resultaten($this->connection);
    }

    public function zaakinformatieobjecten(): Zaakinformatieobjecten
    {
        return new Zaakinformatieobjecten($this->connection);
    }

    public function zaakobjecten(): Zaakobjecten
    {
        return new Zaakobjecten($this->connection);
    }
}
