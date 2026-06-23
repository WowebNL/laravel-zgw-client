<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

/**
 * Thrown when a connection's client_secret does not satisfy the configured strength rules.
 * A weak secret weakens the HS256 signing key and makes JWT forgery more feasible.
 */
class WeakSecretException extends ZgwException {}
