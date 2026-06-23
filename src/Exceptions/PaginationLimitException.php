<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

/**
 * Thrown when auto-pagination exceeds the configured maximum number of pages.
 * Bounds the "next" link loop so a hostile or misbehaving upstream cannot keep
 * a request running indefinitely (availability protection).
 */
class PaginationLimitException extends ZgwException {}
