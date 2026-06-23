<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

use Illuminate\Http\Client\Response;

class ApiRequestException extends ZgwException
{
    public function __construct(
        string $message,
        private readonly Response $response,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
