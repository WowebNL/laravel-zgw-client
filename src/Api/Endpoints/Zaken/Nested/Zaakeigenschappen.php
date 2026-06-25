<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken\Nested;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\ShowsResource;

class Zaakeigenschappen extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, PatchesResource, ReplacesResource, ShowsResource
{
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = '';

    protected string $pathTemplate = '/zaken/{uuid}/zaakeigenschappen';

    public function __construct(ZgwConnection $connection, string $zaakUuid)
    {
        parent::__construct($connection);
        $this->endpoint = 'zaken/'.$this->encodeId($zaakUuid).'/zaakeigenschappen';
    }
}
