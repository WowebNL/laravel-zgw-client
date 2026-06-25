<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\ShowsResource;
use Woweb\Zgw\Data\Attributes\ZgwResource;

// A relation between a zaak and a contactmoment: create, read and delete.
#[ZgwResource(schema: 'ZaakContactMoment', component: 'zaken')]
class Zaakcontactmomenten extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, ShowsResource
{
    use Delete;
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = 'zaakcontactmomenten';
}
