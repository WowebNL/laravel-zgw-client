<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract\Support;

use Closure;
use Woweb\Zgw\Connection\ZgwConnection;
use Woweb\Zgw\Enums\ZgwVersion;
use Woweb\Zgw\Versioning\OperationAvailability;

/**
 * The single declarative map of every operation this package exposes, expressed as the
 * (component, HTTP method, spec path template) it targets plus a closure that drives the client
 * to emit that request. Both the request-contract test and the coverage report consume it, so the
 * client's real surface is defined in exactly one place.
 *
 * Spec path templates are written relative to the API root (matching the OpenAPI `paths` keys,
 * e.g. `/zaken/{uuid}`). Placeholder names are matched positionally, so `{uuid}` here also matches
 * a `{zaak_uuid}` segment in the spec. Each operation's available releases are derived from
 * OperationAvailability, the same source the client guard uses.
 */
final class OperationRegistry
{
    /** A syntactically valid dummy identifier accepted by AbstractEndpoint::encodeId(). */
    public const UUID = 'a1b2c3d4-0000-4000-8000-000000000000';

    /** @var array<string, mixed> */
    private const BODY = ['foo' => 'bar'];

    /**
     * Compact endpoint definitions: component, base resource path, the accessor that returns the
     * endpoint object, and the CRUD action traits it exposes.
     *
     * @return list<array{component: string, base: string, accessor: Closure(ZgwConnection): object, actions: list<string>}>
     */
    private static function endpoints(): array
    {
        $crudPut = ['index', 'show', 'store', 'patch', 'put', 'delete'];

        return [
            // Zaken (zrc)
            ['component' => 'zaken', 'base' => '/zaken', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaken(), 'actions' => $crudPut],
            // A status is append-only: create + read, no delete.
            ['component' => 'zaken', 'base' => '/statussen', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->statussen(), 'actions' => ['index', 'show', 'store']],
            // A rol is immutable: create + read + delete, no update.
            ['component' => 'zaken', 'base' => '/rollen', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->rollen(), 'actions' => ['index', 'show', 'store', 'delete']],
            ['component' => 'zaken', 'base' => '/resultaten', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->resultaten(), 'actions' => $crudPut],
            ['component' => 'zaken', 'base' => '/zaakinformatieobjecten', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaakinformatieobjecten(), 'actions' => $crudPut],
            ['component' => 'zaken', 'base' => '/zaakobjecten', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaakobjecten(), 'actions' => $crudPut],
            ['component' => 'zaken', 'base' => '/klantcontacten', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->klantcontacten(), 'actions' => ['index', 'show', 'store']],
            // Relation resources: create + read + delete, no update.
            ['component' => 'zaken', 'base' => '/zaakcontactmomenten', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaakcontactmomenten(), 'actions' => ['index', 'show', 'store', 'delete']],
            ['component' => 'zaken', 'base' => '/zaakverzoeken', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaakverzoeken(), 'actions' => ['index', 'show', 'store', 'delete']],
            // Zaaknotities are ZGW 1.7+ (enforced by OperationAvailability).
            ['component' => 'zaken', 'base' => '/zaaknotities', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaaknotities(), 'actions' => $crudPut],
            ['component' => 'zaken', 'base' => '/zaken/{uuid}/zaakeigenschappen', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaken()->zaakeigenschappen(self::UUID), 'actions' => $crudPut],
            ['component' => 'zaken', 'base' => '/zaken/{uuid}/besluiten', 'accessor' => fn (ZgwConnection $c) => $c->zaken()->zaken()->besluiten(self::UUID), 'actions' => ['index', 'show', 'store', 'delete']],

            // Catalogi (ztc)
            // A catalogus cannot be deleted; PATCH and PUT exist from ZGW 1.6 (per OperationAvailability).
            ['component' => 'catalogi', 'base' => '/catalogussen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->catalogussen(), 'actions' => ['index', 'show', 'store', 'patch', 'put']],
            ['component' => 'catalogi', 'base' => '/zaaktypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->zaaktypen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/informatieobjecttypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->informatieobjecttypen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/roltypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->roltypen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/statustypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->statustypen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/resultaattypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->resultaattypen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/eigenschappen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->eigenschappen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/besluittypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->besluittypen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/zaakobjecttypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->zaakobjecttypen(), 'actions' => $crudPut],
            ['component' => 'catalogi', 'base' => '/zaaktype-informatieobjecttypen', 'accessor' => fn (ZgwConnection $c) => $c->catalogi()->zaaktypeInformatieobjecttypen(), 'actions' => $crudPut],

            // Documenten (drc)
            ['component' => 'documenten', 'base' => '/enkelvoudiginformatieobjecten', 'accessor' => fn (ZgwConnection $c) => $c->documenten()->enkelvoudiginformatieobjecten(), 'actions' => $crudPut],
            ['component' => 'documenten', 'base' => '/gebruiksrechten', 'accessor' => fn (ZgwConnection $c) => $c->documenten()->gebruiksrechten(), 'actions' => $crudPut],
            // A relation resource: create + read + delete, no update.
            ['component' => 'documenten', 'base' => '/objectinformatieobjecten', 'accessor' => fn (ZgwConnection $c) => $c->documenten()->objectinformatieobjecten(), 'actions' => ['index', 'show', 'store', 'delete']],
            ['component' => 'documenten', 'base' => '/verzendingen', 'accessor' => fn (ZgwConnection $c) => $c->documenten()->verzendingen(), 'actions' => $crudPut],

            // Besluiten (brc)
            ['component' => 'besluiten', 'base' => '/besluiten', 'accessor' => fn (ZgwConnection $c) => $c->besluiten()->besluiten(), 'actions' => $crudPut],
            // A relation resource: create + read + delete, no update.
            ['component' => 'besluiten', 'base' => '/besluitinformatieobjecten', 'accessor' => fn (ZgwConnection $c) => $c->besluiten()->besluitinformatieobjecten(), 'actions' => ['index', 'show', 'store', 'delete']],

            // Autorisaties (ac)
            ['component' => 'autorisaties', 'base' => '/applicaties', 'accessor' => fn (ZgwConnection $c) => $c->autorisaties()->applicaties(), 'actions' => $crudPut],

            // Notificaties (nrc)
            ['component' => 'notificaties', 'base' => '/abonnement', 'accessor' => fn (ZgwConnection $c) => $c->notificaties()->abonnementen(), 'actions' => $crudPut],
            // A kanaal is create + read only.
            ['component' => 'notificaties', 'base' => '/kanaal', 'accessor' => fn (ZgwConnection $c) => $c->notificaties()->kanalen(), 'actions' => ['index', 'show', 'store']],
        ];
    }

    /**
     * Hand-written operations that do not follow the CRUD trait pattern (locking, audit trails,
     * search, publishing, downloads, single-purpose endpoints).
     *
     * @return list<array{key: string, component: string, method: string, path: string, kind: string, invoke: Closure(ZgwConnection): void}>
     */
    private static function customOperations(): array
    {
        return [
            // Zaken
            ['key' => 'zaken.zaken.zoek', 'component' => 'zaken', 'method' => 'post', 'path' => '/zaken/_zoek', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->zaken()->zaken()->zoek()->all()],
            ['key' => 'zaken.zaken.audittrail', 'component' => 'zaken', 'method' => 'get', 'path' => '/zaken/{uuid}/audittrail', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->zaken()->zaken()->audittrail(self::UUID)],
            ['key' => 'zaken.zaken.audittrailItem', 'component' => 'zaken', 'method' => 'get', 'path' => '/zaken/{uuid}/audittrail/{uuid}', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->zaken()->zaken()->audittrailItem(self::UUID, self::UUID)],
            ['key' => 'zaken.zaken.reserveerZaaknummer', 'component' => 'zaken', 'method' => 'post', 'path' => '/zaaknummer_reserveren', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->zaken()->zaken()->reserveerZaaknummer(self::BODY)],

            // Catalogi publish actions
            ['key' => 'catalogi.zaaktypen.publish', 'component' => 'catalogi', 'method' => 'post', 'path' => '/zaaktypen/{uuid}/publish', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->catalogi()->zaaktypen()->publish(self::UUID)],
            ['key' => 'catalogi.informatieobjecttypen.publish', 'component' => 'catalogi', 'method' => 'post', 'path' => '/informatieobjecttypen/{uuid}/publish', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->catalogi()->informatieobjecttypen()->publish(self::UUID)],
            ['key' => 'catalogi.besluittypen.publish', 'component' => 'catalogi', 'method' => 'post', 'path' => '/besluittypen/{uuid}/publish', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->catalogi()->besluittypen()->publish(self::UUID)],

            // Documenten
            ['key' => 'documenten.enkelvoudiginformatieobjecten.lock', 'component' => 'documenten', 'method' => 'post', 'path' => '/enkelvoudiginformatieobjecten/{uuid}/lock', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->documenten()->enkelvoudiginformatieobjecten()->lock(self::UUID)],
            ['key' => 'documenten.enkelvoudiginformatieobjecten.unlock', 'component' => 'documenten', 'method' => 'post', 'path' => '/enkelvoudiginformatieobjecten/{uuid}/unlock', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->documenten()->enkelvoudiginformatieobjecten()->unlock(self::UUID, 'lock-string')],
            ['key' => 'documenten.enkelvoudiginformatieobjecten.audittrail', 'component' => 'documenten', 'method' => 'get', 'path' => '/enkelvoudiginformatieobjecten/{uuid}/audittrail', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->documenten()->enkelvoudiginformatieobjecten()->audittrail(self::UUID)],
            ['key' => 'documenten.enkelvoudiginformatieobjecten.audittrailItem', 'component' => 'documenten', 'method' => 'get', 'path' => '/enkelvoudiginformatieobjecten/{uuid}/audittrail/{uuid}', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->documenten()->enkelvoudiginformatieobjecten()->audittrailItem(self::UUID, self::UUID)],
            ['key' => 'documenten.enkelvoudiginformatieobjecten.download', 'component' => 'documenten', 'method' => 'get', 'path' => '/enkelvoudiginformatieobjecten/{uuid}/download', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->documenten()->enkelvoudiginformatieobjecten()->download(self::UUID)],
            ['key' => 'documenten.enkelvoudiginformatieobjecten.zoek', 'component' => 'documenten', 'method' => 'post', 'path' => '/enkelvoudiginformatieobjecten/_zoek', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->documenten()->enkelvoudiginformatieobjecten()->zoek()->all()],
            ['key' => 'documenten.bestandsdelen.put', 'component' => 'documenten', 'method' => 'put', 'path' => '/bestandsdelen/{uuid}', 'kind' => 'update', 'invoke' => fn (ZgwConnection $c) => $c->documenten()->bestandsdelen()->put(self::UUID, self::BODY)],

            // Besluiten
            ['key' => 'besluiten.besluiten.audittrail', 'component' => 'besluiten', 'method' => 'get', 'path' => '/besluiten/{uuid}/audittrail', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->besluiten()->besluiten()->audittrail(self::UUID)],
            ['key' => 'besluiten.besluiten.audittrailItem', 'component' => 'besluiten', 'method' => 'get', 'path' => '/besluiten/{uuid}/audittrail/{uuid}', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->besluiten()->besluiten()->audittrailItem(self::UUID, self::UUID)],

            // Autorisaties / Notificaties
            ['key' => 'autorisaties.applicaties.consumer', 'component' => 'autorisaties', 'method' => 'get', 'path' => '/applicaties/consumer', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->autorisaties()->applicaties()->consumer('test-client')],
            ['key' => 'notificaties.notificaties.send', 'component' => 'notificaties', 'method' => 'post', 'path' => '/notificaties', 'kind' => 'action', 'invoke' => fn (ZgwConnection $c) => $c->notificaties()->notificaties()->send(self::BODY)],
        ];
    }

    /**
     * The full flattened operation list, each tagged with the releases it is available in.
     *
     * @return list<array{key: string, component: string, method: string, path: string, kind: string, versions: list<string>, invoke: Closure(ZgwConnection): void}>
     */
    public static function all(): array
    {
        $operations = [];

        foreach (self::endpoints() as $endpoint) {
            $base = $endpoint['base'];
            $accessor = $endpoint['accessor'];
            $resource = self::lastSegment($base);

            foreach ($endpoint['actions'] as $action) {
                [$method, $path, $invoke] = self::expandAction($action, $base, $accessor);

                $operations[] = [
                    'key' => "{$endpoint['component']}.{$resource}.{$action}",
                    'component' => $endpoint['component'],
                    'method' => $method,
                    'path' => $path,
                    'kind' => self::kindFor($action),
                    'invoke' => $invoke,
                ];
            }
        }

        $operations = array_merge($operations, self::customOperations());

        return array_map(static function (array $op): array {
            $op['versions'] = array_map(
                static fn (ZgwVersion $v): string => $v->value,
                OperationAvailability::versionsFor($op['component'], $op['method'], $op['path']),
            );

            return $op;
        }, $operations);
    }

    public static function invoker(string $key): Closure
    {
        foreach (self::all() as $operation) {
            if ($operation['key'] === $key) {
                return $operation['invoke'];
            }
        }

        throw new \InvalidArgumentException("Unknown contract operation [{$key}].");
    }

    /**
     * @param  Closure(ZgwConnection): object  $accessor
     * @return array{0: string, 1: string, 2: Closure(ZgwConnection): void}
     */
    private static function expandAction(string $action, string $base, Closure $accessor): array
    {
        return match ($action) {
            // index() is lazy; ->all() realises it so the request is actually issued.
            'index' => ['get', $base, static fn (ZgwConnection $c) => $accessor($c)->index()->all()],
            'show' => ['get', "{$base}/{uuid}", static fn (ZgwConnection $c) => $accessor($c)->show(self::UUID)],
            'store' => ['post', $base, static fn (ZgwConnection $c) => $accessor($c)->store(self::BODY)],
            'patch' => ['patch', "{$base}/{uuid}", static fn (ZgwConnection $c) => $accessor($c)->patch(self::UUID, self::BODY)],
            'put' => ['put', "{$base}/{uuid}", static fn (ZgwConnection $c) => $accessor($c)->put(self::UUID, self::BODY)],
            'delete' => ['delete', "{$base}/{uuid}", static fn (ZgwConnection $c) => $accessor($c)->delete(self::UUID)],
            default => throw new \InvalidArgumentException("Unknown action [{$action}]."),
        };
    }

    private static function kindFor(string $action): string
    {
        return match ($action) {
            'index' => 'list',
            'show' => 'detail',
            'store' => 'create',
            'patch', 'put' => 'update',
            'delete' => 'delete',
            default => 'action',
        };
    }

    private static function lastSegment(string $path): string
    {
        $segments = array_filter(explode('/', $path), static fn ($s) => $s !== '' && ! str_starts_with($s, '{'));

        return (string) end($segments);
    }
}
