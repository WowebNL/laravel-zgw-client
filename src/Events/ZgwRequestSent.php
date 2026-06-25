<?php

declare(strict_types=1);

namespace Woweb\Zgw\Events;

/**
 * Dispatched after a ZGW request receives a response, for request-level audit logging
 * (for example an ISO 27001 audit trail).
 *
 * The event is fired once per HTTP exchange, so when retries are enabled an audit listener
 * sees one event per attempt. Connection-level failures (no response received) do not emit it.
 *
 * Note: $uri carries the full request URL, including query parameters. ZGW filters can contain
 * personal data (a BSN, a case identifier). Redact the URI in the listener before persisting it.
 */
final readonly class ZgwRequestSent
{
    public function __construct(
        public string $connection,
        public string $clientId,
        public string $method,
        public string $uri,
        public int $status,
    ) {}
}
