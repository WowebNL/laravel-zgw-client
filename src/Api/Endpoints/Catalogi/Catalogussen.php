<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Catalogi;

use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

// The ZGW Catalogi API does not allow deleting a catalogus. PATCH was added in ZGW 1.6.
class Catalogussen extends AbstractEndpoint
{
    use Index;
    use Patch;
    use Show;
    use Store;

    protected string $apiName = 'catalogi';

    protected string $endpoint = 'catalogussen';
}
