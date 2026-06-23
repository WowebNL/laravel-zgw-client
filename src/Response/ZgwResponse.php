<?php

declare(strict_types=1);

namespace Woweb\Zgw\Response;

use Illuminate\Http\Client\Response;
use Woweb\Zgw\Exceptions\ApiRequestException;

class ZgwResponse
{
    /**
     * Validate a standard JSON response and return its decoded body.
     *
     * @return array<string, mixed>
     *
     * @throws ApiRequestException
     */
    public function validate(Response $response): array
    {
        if ($response->failed()) {
            // The response body may contain citizen PII, so it is kept off the exception
            // message (which is auto-logged) and remains available via $exception->getResponse().
            throw new ApiRequestException(
                "ZGW API request failed [{$response->status()}].",
                $response,
                $response->status(),
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Validate a delete response (expects 204 No Content).
     *
     * @throws ApiRequestException
     */
    public function validateDelete(Response $response): void
    {
        if ($response->status() !== 204) {
            // Body kept off the message (auto-logged); inspect it via $exception->getResponse().
            throw new ApiRequestException(
                "ZGW API delete request failed [{$response->status()}].",
                $response,
                $response->status(),
            );
        }
    }
}
