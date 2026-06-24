<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

/**
 * Thrown when an operation is called on a connection whose targeted ZGW version does not define it,
 * for example calling a 1.7-only endpoint on a connection configured for ZGW 1.5.
 */
class UnsupportedOperationException extends ZgwException {}
