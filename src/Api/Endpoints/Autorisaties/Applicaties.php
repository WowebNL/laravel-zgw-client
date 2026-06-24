<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Autorisaties;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Put;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

class Applicaties extends AbstractEndpoint
{
    use Delete;
    use Index;
    use Patch;
    use Put;
    use Show;
    use Store;

    protected string $apiName = 'autorisaties';

    protected string $endpoint = 'applicaties';

    /**
     * Look up the applicatie (authorisations) registered for a given client id.
     *
     * @return array<string, mixed>
     */
    public function consumer(string $clientId): array
    {
        $url = $this->baseUrl.$this->endpoint.'/consumer';
        $response = $this->connection->request()->get($url, ['clientId' => $clientId]);

        return $this->zgwResponse->validate($response);
    }
}
