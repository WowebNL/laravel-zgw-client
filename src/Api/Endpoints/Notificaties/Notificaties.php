<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Notificaties;

use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

class Notificaties extends AbstractEndpoint
{
    protected string $apiName = 'notificaties';

    protected string $endpoint = 'notificaties';

    /**
     * Publish a notification to the Notificaties API.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function send(array $params): array
    {
        $url = $this->baseUrl.$this->endpoint;
        $response = $this->connection->request()->post($url, $params);

        return $this->zgwResponse->validate($response);
    }
}
