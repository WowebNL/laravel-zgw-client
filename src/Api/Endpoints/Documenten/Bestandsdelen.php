<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Documenten;

use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

/**
 * Bestandsdelen are file part chunks for multi-part document uploads.
 * The only operation is PUT (upload a chunk).
 */
class Bestandsdelen extends AbstractEndpoint
{
    protected string $apiName = 'documenten';

    protected string $endpoint = 'bestandsdelen';

    /**
     * Upload a file part chunk.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function put(string $uuid, array $params): array
    {
        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid);
        $response = $this->connection->request()->put($url, $params);

        return $this->zgwResponse->validate($response);
    }
}
