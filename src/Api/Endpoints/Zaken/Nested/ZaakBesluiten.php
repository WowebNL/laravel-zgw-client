<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken\Nested;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Attributes\ZgwResource;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\ShowsResource;

// The besluiten linked to a specific zaak. A relation resource: create, read and delete.
#[ZgwResource(schema: 'ZaakBesluit', component: 'zaken')]
class ZaakBesluiten extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, ShowsResource
{
    use Delete;
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = '';

    protected string $pathTemplate = '/zaken/{uuid}/besluiten';

    public function __construct(ZgwConnection $connection, string $zaakUuid)
    {
        parent::__construct($connection);
        $this->endpoint = 'zaken/'.$this->encodeId($zaakUuid).'/besluiten';
    }
}
