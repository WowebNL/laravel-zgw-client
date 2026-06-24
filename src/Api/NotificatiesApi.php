<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api;

use Woweb\Zgw\Api\Endpoints\Notificaties\Abonnementen;
use Woweb\Zgw\Api\Endpoints\Notificaties\Kanalen;
use Woweb\Zgw\Api\Endpoints\Notificaties\Notificaties;
use Woweb\Zgw\Connection\ZgwConnection;

class NotificatiesApi
{
    public function __construct(private readonly ZgwConnection $connection) {}

    public function abonnementen(): Abonnementen
    {
        return new Abonnementen($this->connection);
    }

    public function kanalen(): Kanalen
    {
        return new Kanalen($this->connection);
    }

    public function notificaties(): Notificaties
    {
        return new Notificaties($this->connection);
    }
}
