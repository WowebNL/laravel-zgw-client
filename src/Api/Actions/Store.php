<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Actions;

trait Store
{
    /**
     * Create a new resource.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function store(array $params): array
    {
        $url = $this->baseUrl.$this->endpoint;
        $response = $this->connection->request()->post($url, $params);

        return $this->zgwResponse->validate($response);
    }
}
