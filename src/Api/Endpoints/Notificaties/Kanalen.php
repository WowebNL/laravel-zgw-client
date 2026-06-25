<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Notificaties;

use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\ShowsResource;
use Woweb\Zgw\Data\Attributes\ZgwResource;

// A kanaal (notification channel) is create + read only in the ZGW Notificaties API.
#[ZgwResource(schema: 'Kanaal', component: 'notificaties')]
class Kanalen extends AbstractEndpoint implements CreatesResource, ListsResources, ShowsResource
{
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'notificaties';

    protected string $endpoint = 'kanaal';
}
