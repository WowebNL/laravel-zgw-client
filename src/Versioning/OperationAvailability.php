<?php

declare(strict_types=1);

namespace Woweb\Zgw\Versioning;

use Woweb\Zgw\Enums\ZgwVersion;

/**
 * Per-version availability of ZGW operations.
 *
 * Almost every operation exists in all supported releases, so only the exceptions are listed here:
 * each entry maps a "{component} {METHOD} {path-template}" key to the releases that define it.
 * Anything not listed is available in every release. This map is verified against the OpenAPI specs
 * by the contract test suite, so it cannot drift from the standard unnoticed.
 */
final class OperationAvailability
{
    /**
     * Operations that are NOT available in every release, keyed by "{component} {METHOD} {path}".
     *
     * @return array<string, list<ZgwVersion>>
     */
    private static function restricted(): array
    {
        return [
            // Zaaknotities was introduced in ZGW 1.7.
            'zaken GET /zaaknotities' => [ZgwVersion::V1_7],
            'zaken POST /zaaknotities' => [ZgwVersion::V1_7],
            'zaken GET /zaaknotities/{uuid}' => [ZgwVersion::V1_7],
            'zaken PUT /zaaknotities/{uuid}' => [ZgwVersion::V1_7],
            'zaken PATCH /zaaknotities/{uuid}' => [ZgwVersion::V1_7],
            'zaken DELETE /zaaknotities/{uuid}' => [ZgwVersion::V1_7],
            'zaken POST /zaaknummer_reserveren' => [ZgwVersion::V1_7],

            // Updating a catalogus was added in ZGW 1.6.
            'catalogi PUT /catalogussen/{uuid}' => [ZgwVersion::V1_6, ZgwVersion::V1_7],
            'catalogi PATCH /catalogussen/{uuid}' => [ZgwVersion::V1_6, ZgwVersion::V1_7],
        ];
    }

    /**
     * The releases in which an operation is available.
     *
     * @return list<ZgwVersion>
     */
    public static function versionsFor(string $component, string $method, string $pathTemplate): array
    {
        return self::restricted()[self::key($component, $method, $pathTemplate)] ?? ZgwVersion::cases();
    }

    /**
     * Whether an operation is available in the given release.
     */
    public static function isAvailable(string $component, string $method, string $pathTemplate, ZgwVersion $version): bool
    {
        return in_array($version, self::versionsFor($component, $method, $pathTemplate), true);
    }

    private static function key(string $component, string $method, string $pathTemplate): string
    {
        return "{$component} ".strtoupper($method)." {$pathTemplate}";
    }
}
