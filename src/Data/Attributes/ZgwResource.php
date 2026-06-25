<?php

declare(strict_types=1);

namespace Woweb\Zgw\Data\Attributes;

use Attribute;

/**
 * Declares which ZGW schema an endpoint returns, so the DTO generator can map the endpoint to its
 * generated DTO without a hand-maintained list.
 *
 * The single manual step when adding a typed resource is annotating the endpoint with the schema
 * it returns, which you have to know anyway. A contract test enforces that the named schema exists
 * and that the mapping is generated.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ZgwResource
{
    public function __construct(
        public string $schema,
        public string $component,
    ) {}
}
