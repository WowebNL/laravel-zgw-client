<?php

declare(strict_types=1);

namespace Woweb\Zgw\Facades;

use Illuminate\Support\Facades\Facade;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\ZgwManager;

/**
 * @method static ZgwConnection connection(?string $name = null)
 *
 * @see ZgwManager
 */
class Zgw extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ZgwManager::class;
    }
}
