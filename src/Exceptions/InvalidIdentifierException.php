<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

/**
 * Thrown when a resource identifier placed into a request URL contains characters that could
 * manipulate the request path or query (path traversal, query/fragment injection). Identifiers
 * are restricted to letters, digits, hyphen and underscore.
 */
class InvalidIdentifierException extends ZgwException {}
