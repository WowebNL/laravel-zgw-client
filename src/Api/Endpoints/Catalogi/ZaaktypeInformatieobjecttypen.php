<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Catalogi;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\ShowsResource;
use Woweb\Zgw\Data\Attributes\ZgwResource;

// Relations between a zaaktype and its informatieobjecttypen.
#[ZgwResource(schema: 'ZaakTypeInformatieObjectType', component: 'catalogi')]
class ZaaktypeInformatieobjecttypen extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, PatchesResource, ReplacesResource, ShowsResource
{
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'catalogi';

    protected string $endpoint = 'zaaktype-informatieobjecttypen';
}
