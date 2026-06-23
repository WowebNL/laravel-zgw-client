<?php

declare(strict_types=1);

namespace Woweb\Zgw\Contracts;

interface AuthorizationInterface
{
    /**
     * Generate a Bearer token string for the given connection config.
     *
     * @param  array<string, mixed>  $config
     */
    public function getToken(array $config): string;
}
