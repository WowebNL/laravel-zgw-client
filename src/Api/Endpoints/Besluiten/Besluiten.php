<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Besluiten;

use Woweb\Zgw\Api\Actions\Audittrail;
use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Attributes\ZgwResource;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ProvidesAuditTrail;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\ShowsResource;

#[ZgwResource(schema: 'Besluit', component: 'besluiten')]
class Besluiten extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, PatchesResource, ProvidesAuditTrail, ReplacesResource, ShowsResource
{
    use Audittrail;
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'besluiten';

    protected string $endpoint = 'besluiten';
}
