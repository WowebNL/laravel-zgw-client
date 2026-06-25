<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken;

use Woweb\Zgw\Api\Actions\Audittrail;
use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Search;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Api\Endpoints\Zaken\Nested\ZaakBesluiten;
use Woweb\Zgw\Api\Endpoints\Zaken\Nested\Zaakeigenschappen;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ProvidesAuditTrail;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\SearchesResources;
use Woweb\Zgw\Contracts\ShowsResource;

class Zaken extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, PatchesResource, ProvidesAuditTrail, ReplacesResource, SearchesResources, ShowsResource
{
    use Audittrail;
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Search;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = 'zaken';

    public function zaakeigenschappen(string $zaakUuid): Zaakeigenschappen
    {
        return new Zaakeigenschappen($this->connection, $zaakUuid);
    }

    public function besluiten(string $zaakUuid): ZaakBesluiten
    {
        return new ZaakBesluiten($this->connection, $zaakUuid);
    }

    /**
     * Reserve one or more zaak identifications up front (ZGW 1.7+).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function reserveerZaaknummer(array $params = []): array
    {
        $this->assertSupported('POST', '/zaaknummer_reserveren');

        $url = $this->baseUrl.'zaaknummer_reserveren';

        return $this->zgwResponse->validate($this->connection->request()->post($url, $params));
    }
}
