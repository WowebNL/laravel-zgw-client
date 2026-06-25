<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken;

use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Attributes\ZgwResource;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\ShowsResource;

// A status is append-only in the ZGW Zaken API: it supports create and read, but not delete.
#[ZgwResource(schema: 'Status', component: 'zaken')]
class Statussen extends AbstractEndpoint implements CreatesResource, ListsResources, ShowsResource
{
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = 'statussen';
}
