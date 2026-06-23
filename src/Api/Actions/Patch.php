<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

trait Patch
{
    /**
     * Partially update a resource.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function patch(string $uuid, array $params): array
    {
        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid);
        $response = $this->connection->request()->patch($url, $params);

        return $this->zgwResponse->validate($response);
    }
}
