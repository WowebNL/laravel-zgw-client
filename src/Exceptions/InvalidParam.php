<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

/**
 * A single field-level error from a ZGW "ValidatieFout" (HTTP 400) response body. The ZGW APIs
 * return validation failures in a standard RFC 7807 problem+json shape with an `invalidParams`
 * array; each entry is mapped to one of these.
 */
final readonly class InvalidParam
{
    public function __construct(
        public string $name,
        public string $code,
        public string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $param
     */
    public static function fromArray(array $param): self
    {
        return new self(
            (string) ($param['name'] ?? ''),
            (string) ($param['code'] ?? ''),
            (string) ($param['reason'] ?? ''),
        );
    }
}
