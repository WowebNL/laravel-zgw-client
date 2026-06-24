<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Notificaties;

use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

// A kanaal (notification channel) is create + read only in the ZGW Notificaties API.
class Kanalen extends AbstractEndpoint
{
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'notificaties';

    protected string $endpoint = 'kanaal';
}
