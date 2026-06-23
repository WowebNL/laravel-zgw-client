<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Besluiten;

use Illuminate\Support\Collection;
use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

class Besluiten extends AbstractEndpoint
{
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'besluiten';

    protected string $endpoint = 'besluiten';

    /**
     * Retrieve the audit trail for a besluit.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function audittrail(string $uuid): Collection
    {
        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/audittrail';
        $response = $this->connection->request()->get($url);

        /** @var array<int, array<string, mixed>> $items */
        $items = $this->zgwResponse->validate($response);

        return $this->createCollection($items);
    }
}
