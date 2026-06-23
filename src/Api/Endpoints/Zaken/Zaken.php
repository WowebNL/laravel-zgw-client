<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Api\Endpoints\Zaken\Nested\Zaakeigenschappen;

class Zaken extends AbstractEndpoint
{
    use Delete;
    use Index;
    use Patch;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = 'zaken';

    public function zaakeigenschappen(string $zaakUuid): Zaakeigenschappen
    {
        return new Zaakeigenschappen($this->connection, $zaakUuid);
    }
}
