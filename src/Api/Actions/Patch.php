<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Woweb\Zgw\Contracts\PatchesResource;

/**
 * @phpstan-require-implements PatchesResource
 */
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
        $this->assertSupported('PATCH', $this->itemTemplate());

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid);
        $response = $this->connection->request()->patch($url, $params);

        return $this->zgwResponse->validate($response);
    }
}
