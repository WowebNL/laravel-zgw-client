<?php

declare(strict_types=1);

namespace Woweb\Zgw;

use Illuminate\Support\ServiceProvider;
use Woweb\Zgw\Auth\Authorization;
use Woweb\Zgw\Contracts\AuthorizationInterface;

class ZgwServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/zgw.php', 'zgw');

        $this->app->singleton(AuthorizationInterface::class, Authorization::class);

        $this->app->singleton(ZgwManager::class, function ($app): ZgwManager {
            return new ZgwManager($app->make(AuthorizationInterface::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/zgw.php' => config_path('zgw.php'),
            ], 'zgw-config');
        }
    }
}
