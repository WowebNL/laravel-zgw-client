<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api;

use Woweb\Zgw\Api\Endpoints\Documenten\Bestandsdelen;
use Woweb\Zgw\Api\Endpoints\Documenten\Enkelvoudiginformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Documenten\Gebruiksrechten;
use Woweb\Zgw\Api\Endpoints\Documenten\Objectinformatieobjecten;
use Woweb\Zgw\Api\Endpoints\Documenten\Verzendingen;
use Woweb\Zgw\Connection\ZgwConnection;

class DocumentenApi
{
    public function __construct(private readonly ZgwConnection $connection) {}

    public function enkelvoudiginformatieobjecten(): Enkelvoudiginformatieobjecten
    {
        return new Enkelvoudiginformatieobjecten($this->connection);
    }

    public function gebruiksrechten(): Gebruiksrechten
    {
        return new Gebruiksrechten($this->connection);
    }

    public function objectinformatieobjecten(): Objectinformatieobjecten
    {
        return new Objectinformatieobjecten($this->connection);
    }

    public function verzendingen(): Verzendingen
    {
        return new Verzendingen($this->connection);
    }

    public function bestandsdelen(): Bestandsdelen
    {
        return new Bestandsdelen($this->connection);
    }
}
