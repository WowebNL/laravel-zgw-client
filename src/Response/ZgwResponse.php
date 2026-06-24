<?php

declare(strict_types=1);

namespace Woweb\Zgw\Response;

use Illuminate\Http\Client\Response;
use Woweb\Zgw\Exceptions\ApiRequestException;
use Woweb\Zgw\Exceptions\ValidationException;

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
            $this->fail($response, 'request');
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
            $this->fail($response, 'delete request');
        }
    }

    /**
     * Throw the appropriate exception for a failed response.
     *
     * A structured ZGW "ValidatieFout" body (an `invalidParams` array) becomes a
     * ValidationException with typed field access; anything else becomes a plain
     * ApiRequestException. In both cases the (potentially PII-bearing) body is kept off the
     * exception message, which is auto-logged, and stays reachable via $exception->getResponse().
     *
     * @throws ApiRequestException
     */
    private function fail(Response $response, string $action): never
    {
        $body = $response->json();

        if (is_array($body) && isset($body['invalidParams'])) {
            throw new ValidationException(
                "ZGW API {$action} failed validation [{$response->status()}].",
                $response,
                $response->status(),
            );
        }

        throw new ApiRequestException(
            "ZGW API {$action} failed [{$response->status()}].",
            $response,
            $response->status(),
        );
    }
}
