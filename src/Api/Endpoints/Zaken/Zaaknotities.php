<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken;

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
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\ShowsResource;

// Zaaknotities were introduced in ZGW 1.7; the per-version guard rejects them on older connections.
#[ZgwResource(schema: 'ZaakNotitie', component: 'zaken')]
class Zaaknotities extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, PatchesResource, ReplacesResource, ShowsResource
{
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = 'zaaknotities';
}
