<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Catalogi;

use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\ShowsResource;

// The ZGW Catalogi API does not allow deleting a catalogus. PATCH and PUT were added in ZGW 1.6
// and are rejected by the per-version guard on a 1.5 connection.
class Catalogussen extends AbstractEndpoint implements CreatesResource, ListsResources, PatchesResource, ReplacesResource, ShowsResource
{
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'catalogi';

    protected string $endpoint = 'catalogussen';
}
