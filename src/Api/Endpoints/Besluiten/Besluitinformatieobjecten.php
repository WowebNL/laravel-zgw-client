<?php

declare(strict_types=1);

namespace Woweb\Zgw\Api\Endpoints\Besluiten;

use Woweb\Zgw\Api\Actions\Delete;
use Woweb\Zgw\Api\Actions\Index;
use Woweb\Zgw\Api\Actions\Show;
use Woweb\Zgw\Api\Actions\Store;
use Woweb\Zgw\Api\Endpoints\AbstractEndpoint;

// A besluit-informatieobject is a relation: the ZGW Besluiten API supports create, read and
// delete, but not update.
class Besluitinformatieobjecten extends AbstractEndpoint
{
    use Delete;
    use Index;
    use Show;
    use Store;

    protected string $apiName = 'besluiten';

    protected string $endpoint = 'besluitinformatieobjecten';
}
