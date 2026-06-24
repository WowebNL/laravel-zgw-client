<?php

declare(strict_types=1);

namespace Woweb\Zgw\Enums;

use Woweb\Zgw\Exceptions\InvalidConfigurationException;

/**
 * The ZGW standard release a connection targets.
 *
 * The standard is published as umbrella releases (1.5, 1.6, 1.7), each pinning its own set of
 * per-component API versions. A connection records which release it talks to, so calling code can
 * branch on it (for example only using endpoints introduced in a later release).
 */
enum ZgwVersion: string
{
    case V1_5 = '1.5';
    case V1_6 = '1.6';
    case V1_7 = '1.7';

    /**
     * The newest supported release.
     */
    public static function latest(): self
    {
        return self::V1_7;
    }

    /**
     * Resolve a version from configuration, throwing on an unsupported value.
     *
     * @throws InvalidConfigurationException
     */
    public static function fromConfig(string $version): self
    {
        return self::tryFrom($version) ?? throw new InvalidConfigurationException(
            "Unsupported ZGW version [{$version}]. Supported versions: ".
            implode(', ', array_map(static fn (self $v): string => $v->value, self::cases())).'.'
        );
    }

    /**
     * Whether this version is the same as or newer than another.
     */
    public function isAtLeast(self $other): bool
    {
        return version_compare($this->value, $other->value, '>=');
    }

    /**
     * Human-readable label, e.g. "ZGW 1.7".
     */
    public function label(): string
    {
        return 'ZGW '.$this->value;
    }
}
