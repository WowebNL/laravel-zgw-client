<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Woweb\Zgw\Contracts\ReplacesResource;

/**
 * @phpstan-require-implements ReplacesResource
 */
trait Put
{
    /**
     * Replace a resource (full update).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function put(string $uuid, array $params): array
    {
        $this->assertSupported('PUT', $this->itemTemplate());

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid);
        $response = $this->connection->request()->put($url, $params);

        return $this->zgwResponse->validate($response);
    }
}
