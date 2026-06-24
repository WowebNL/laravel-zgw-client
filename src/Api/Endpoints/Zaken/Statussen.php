<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken;

use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

// A status is append-only in the ZGW Zaken API: it supports create and read, but not delete.
class Statussen extends AbstractEndpoint
{
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = 'statussen';
}
