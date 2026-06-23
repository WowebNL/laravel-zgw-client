<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api;

use Woweb\Zgw\Api\Endpoints\Catalogi\Catalogussen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Eigenschappen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Informatieobjecttypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Resultaattypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Roltypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Statustypen;
use Woweb\Zgw\Api\Endpoints\Catalogi\Zaaktypen;
use Woweb\Zgw\Connection\ZgwConnection;

class CatalogiApi
{
    public function __construct(private readonly ZgwConnection $connection) {}

    public function catalogussen(): Catalogussen
    {
        return new Catalogussen($this->connection);
    }

    public function zaaktypen(): Zaaktypen
    {
        return new Zaaktypen($this->connection);
    }

    public function informatieobjecttypen(): Informatieobjecttypen
    {
        return new Informatieobjecttypen($this->connection);
    }

    public function roltypen(): Roltypen
    {
        return new Roltypen($this->connection);
    }

    public function statustypen(): Statustypen
    {
        return new Statustypen($this->connection);
    }

    public function resultaattypen(): Resultaattypen
    {
        return new Resultaattypen($this->connection);
    }

    public function eigenschappen(): Eigenschappen
    {
        return new Eigenschappen($this->connection);
    }
}
