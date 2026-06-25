<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Documenten;

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

// An object-informatieobject is a relation: the ZGW Documenten API supports create, read and
// delete, but not update.
#[ZgwResource(schema: 'ObjectInformatieObject', component: 'documenten')]
class Objectinformatieobjecten extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, ShowsResource
{
    use Delete;
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'documenten';

    protected string $endpoint = 'objectinformatieobjecten';
}
