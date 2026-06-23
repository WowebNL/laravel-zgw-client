<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Documenten;

use Illuminate\Support\Collection;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

class Enkelvoudiginformatieobjecten extends AbstractEndpoint
{
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'documenten';

    protected string $endpoint = 'enkelvoudiginformatieobjecten';

    /**
     * Lock a document for editing.
     *
     * Returns the lock string that must be passed to subsequent write operations.
     */
    public function lock(string $uuid): string
    {
        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/lock';
        $response = $this->connection->request()->post($url);

        return $this->zgwResponse->validate($response)['lock'] ?? '';
    }

    /**
     * Unlock a previously locked document.
     *
     * @return array<string, mixed>|null
     */
    public function unlock(string $uuid, string $lockString): ?array
    {
        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/unlock';
        $response = $this->connection->request()
            ->post($url, ['lock' => $lockString]);

        if ($response->noContent()) {
            return null;
        }

        return $this->zgwResponse->validate($response);
    }

    /**
     * Retrieve the audit trail for a document.
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
