<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

use Woweb\Zgw\Api\Actions\Publish;

/**
 * An endpoint that can publish a concept resource, making it final (POST item/publish).
 *
 * @see Publish
 */
interface PublishesResource
{
    /**
     * Publish a concept catalogue type, making it final.
     *
     * @return array<string, mixed>
     */
    public function publish(string $uuid): array;
}
