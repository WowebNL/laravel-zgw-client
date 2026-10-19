<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints;

use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\DisallowedHostException;

/**
 * Resolves a resource directly by its full URL rather than via apiName/endpoint.
 * Used for following hyperlinks returned in API responses.
 *
 * Every URL is checked against the connection allowlist before a request is sent, so the
 * bearer token never leaves for an untrusted origin.
 */
class DirectEndpoint extends AbstractEndpoint
{
    /**
     * Fetch a resource by its full URL and return the decoded response.
     *
     * Like the items returned by index(), a `uuid` is derived from the resource's `url` field when
     * the response does not carry one itself.
     *
     * @return array<string, mixed>
     *
     * @throws DisallowedHostException when the URL is not on the connection allowlist.
     * @throws ApiRequestException on a 4xx or 5xx response.
     */
    public function getByUrl(string $url): array
    {
        $this->connection->assertUrlAllowed($url);

        $response = $this->connection->request()->get($url);

        return $this->withDerivedUuid($this->zgwResponse->validate($response));
    }

    /**
     * Fetch the raw response body of a URL, for example the `inhoud` link of a document.
     *
     * The body is returned byte for byte, so binary content is preserved. The connection sends
     * `Accept: application/json` on every request; this request replaces it with a wildcard so
     * a server that returns file content does not respond `406 Not Acceptable`.
     *
     * @throws DisallowedHostException when the URL is not on the connection allowlist.
     * @throws ApiRequestException on a 4xx or 5xx response, so an error page is never returned
     *                             as content.
     */
    public function getRawByUrl(string $url): string
    {
        $this->connection->assertUrlAllowed($url);

        $response = $this->connection->request()
            ->replaceHeaders(['Accept' => '*/*'])
            ->get($url);

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
