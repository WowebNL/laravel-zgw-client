<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Woweb\Zgw\Exceptions\ApiRequestException;

trait Delete
{
    /**
     * Delete a resource by UUID.
     *
     * Returns true on success (HTTP 204).
     *
     * @throws ApiRequestException
     */
    public function delete(string $uuid): bool
    {
        $this->assertSupported('DELETE', $this->itemTemplate());

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid);
        $response = $this->connection->request()->delete($url);

        $this->zgwResponse->validateDelete($response);

        return true;
    }
}
