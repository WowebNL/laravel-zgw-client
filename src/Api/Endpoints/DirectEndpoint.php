<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints;

/**
 * Resolves a resource directly by its full URL rather than via apiName/endpoint.
 * Used for following hyperlinks returned in API responses.
 */
class DirectEndpoint extends AbstractEndpoint
{
    /**
     * Fetch a resource by its full URL and return the decoded response.
     *
     * @return array<string, mixed>
     */
    public function getByUrl(string $url): array
    {
        $this->connection->assertUrlAllowed($url);

        $response = $this->connection->request()->get($url);

        return $this->zgwResponse->validate($response);
    }
}
