<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Zaken\Nested;

use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Patch;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;
use Woweb\Zgw\Connection\ZgwConnection;

class Zaakeigenschappen extends AbstractEndpoint
{
    use Index;
    use Patch;
    use Show;
    use Store;

    protected string $apiName = 'zaken';

    protected string $endpoint = '';

    public function __construct(ZgwConnection $connection, string $zaakUuid)
    {
        parent::__construct($connection);
        $this->endpoint = 'zaken/'.$this->encodeId($zaakUuid).'/zaakeigenschappen';
    }
}
