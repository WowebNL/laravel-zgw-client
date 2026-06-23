<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

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
        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid);
        $response = $this->connection->request()->put($url, $params);

        return $this->zgwResponse->validate($response);
    }
}
