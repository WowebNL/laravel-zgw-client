<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

use Woweb\Zgw\Contracts\PublishesResource;

/**
 * @phpstan-require-implements PublishesResource
 */
trait Publish
{
    /**
     * Publish a concept catalogue type, making it final.
     *
     * @return array<string, mixed>
     */
    public function publish(string $uuid): array
    {
        $this->assertSupported('POST', $this->itemTemplate().'/publish');

        $url = $this->baseUrl.$this->endpoint.'/'.$this->encodeId($uuid).'/publish';

        return $this->zgwResponse->validate($this->connection->request()->post($url));
    }
}
