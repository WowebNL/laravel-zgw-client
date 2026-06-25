<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Documenten;

use Woweb\Zgw\Api\Actions\Audittrail;
use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Search;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\DeletesResource;
use Woweb\Zgw\Contracts\ListsResources;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ProvidesAuditTrail;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Contracts\SearchesResources;
use Woweb\Zgw\Contracts\ShowsResource;
use Woweb\Zgw\Exceptions\ApiRequestException;

class Enkelvoudiginformatieobjecten extends AbstractEndpoint implements CreatesResource, DeletesResource, ListsResources, PatchesResource, ProvidesAuditTrail, ReplacesResource, SearchesResources, ShowsResource
{
    use Audittrail;
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Search;
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
     * Download the binary content of a document.
     *
     * @throws ApiRequestException
     */
    public function download(string $uuid): string
    {
        $this->assertSupported('GET', $this->itemTemplate().'/download');

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/download';
        $response = $this->connection->request()->get($url);

        if ($response->failed()) {
            throw new ApiRequestException(
                "ZGW API request failed [{$response->status()}].",
                $response,
                $response->status(),
            );
        }

        return $response->body();
    }
}
