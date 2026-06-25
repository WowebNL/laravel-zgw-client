<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Exceptions\ApiRequestException;

/**
 * An endpoint that can delete a resource (DELETE item).
 *
 * @see Delete
 */
interface DeletesResource
{
    /**
     * Delete a resource by UUID.
     *
     * Returns true on success (HTTP 204).
     *
     * @throws ApiRequestException
     */
    public function delete(string $uuid): bool;
}
