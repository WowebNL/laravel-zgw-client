<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Attributes\ZgwResource;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\ShowsResource;

// A rol is immutable in the ZGW Zaken API: it supports create, read and delete, but not update.
#[ZgwResource(schema: 'Rol', component: 'zaken')]
class Rollen extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, ShowsResource
{
    use Delete;
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = 'rollen';
}
