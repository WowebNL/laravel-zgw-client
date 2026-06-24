<?php

declare(strict_types=1);

namespace Woweb\Zgw\Exceptions;

/**
 * Thrown when a ZGW API rejects a write with a structured validation error (a "ValidatieFout",
 * typically HTTP 400). Extends ApiRequestException, so existing `catch (ApiRequestException)`
 * handlers keep working, while adding typed access to the per-field failures.
 *
 * As with ApiRequestException, the (potentially PII-bearing) body is kept off the exception
 * message and is only reachable through these accessors and getResponse().
 */
class ValidationException extends ApiRequestException
{
    /**
     * The field-level validation errors.
     *
     * @return list<InvalidParam>
     */
    public function invalidParams(): array
    {
        $params = $this->body()['invalidParams'] ?? [];

        if (! is_array($params)) {
            return [];
        }

        return array_values(array_map(
            static fn ($param): InvalidParam => InvalidParam::fromArray(is_array($param) ? $param : []),
            $params,
        ));
    }

    /**
     * The top-level ZGW error code (for example "invalid").
     */
    public function validationCode(): ?string
    {
        return $this->field('code');
    }

    public function title(): ?string
    {
        return $this->field('title');
    }

    public function detail(): ?string
    {
        return $this->field('detail');
    }

    private function field(string $key): ?string
    {
        $value = $this->body()[$key] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $body = $this->getResponse()->json();

        return is_array($body) ? $body : [];
    }
}
