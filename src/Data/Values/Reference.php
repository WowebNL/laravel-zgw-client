<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Values;

use Stringable;

/**
 * A typed reference to another ZGW resource by its URL.
 *
 * ZGW links resources by absolute URL (a "hyperlinked" API). A Reference carries that URL and
 * nothing more: it does no I/O. Resolving a reference into the referenced resource is the job of
 * a separate repository layer, so DTOs stay pure data.
 */
final readonly class Reference implements Stringable
{
    public function __construct(
        public string $url,
    ) {}

    /**
     * The trailing path segment of the URL, which for ZGW resources is the UUID.
     */
    public function uuid(): ?string
    {
        $path = parse_url($this->url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $segment = substr($path, strrpos($path, '/') + 1);

        return $segment === '' ? null : $segment;
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
