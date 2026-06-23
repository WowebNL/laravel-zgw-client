<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

/**
 * Thrown when a request target (a pagination "next" link or a direct URL) resolves
 * to an origin that is not part of the connection's allowlist. This prevents the
 * Authorization bearer token from being sent to an untrusted host (SSRF / credential leakage).
 */
class DisallowedHostException extends ZgwException {}
