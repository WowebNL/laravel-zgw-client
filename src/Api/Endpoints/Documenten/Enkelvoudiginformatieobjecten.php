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
use Woweb\Zgw\Api\Attributes\ZgwResource;
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

#[ZgwResource(schema: 'EnkelvoudigInformatieObject', component: 'documenten')]
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

        return $this->zgwResponse->validate($this->postWithoutBody($url))['lock'] ?? '';
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
     * Accepts the query parameters the standard defines for this operation, in particular `versie`
     * and `registratieOp` to retrieve a specific or point-in-time version of the content.
     *
     * The connection sends `Accept: application/json` on every request, but this endpoint returns
     * the raw file content, so that Accept is replaced with a wildcard that permits any media type.
     * Otherwise the server cannot satisfy the requested media type and responds `406 Not Acceptable`.
     *
     * @param  array<string, mixed>  $params  Optional query parameters (for example `versie`).
     *
     * @throws ApiRequestException
     */
    public function download(string $uuid, array $params = []): string
    {
        $this->assertSupported('GET', $this->itemTemplate().'/download');

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/download';
        $response = $this->connection->request()
            ->replaceHeaders(['Accept' => '*/*'])
            ->get($url, $params);

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
