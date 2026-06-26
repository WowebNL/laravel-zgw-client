<?php

declare(strict_types=1);

/**
 * Regenerates the typed read DTOs from the pinned ZGW OpenAPI specs.
 *
 * The set of resources is discovered from the #[ZgwResource] attributes on the endpoints, so adding
 * a typed resource is just annotating its endpoint. DTOs are generated per component into
 * src/Data/Generated/{Component} (schema names are not unique across components, so each component
 * gets its own namespace).
 *
 * Run with `composer dto:generate`. Requires the spec fixtures to be present (run
 * `composer contract:fetch` first). The composer script also runs Pint over the output, so the
 * generated files are formatted identically to how they are committed and a regenerate with no
 * spec change yields no diff. The generated files under src/Data/Generated are committed, so the
 * runtime never carries the generator. Review the git diff of that directory after running.
 */

require __DIR__.'/../vendor/autoload.php';

use Woweb\Zgw\Contracts\CreatesResource;
use Woweb\Zgw\Contracts\PatchesResource;
use Woweb\Zgw\Contracts\ReplacesResource;
use Woweb\Zgw\Data\Casts\GeoJsonCast;
use Woweb\Zgw\Data\Values\GeoJsonGeometry;
use Woweb\Zgw\Dev\Dto\DtoGenerator;
use Woweb\Zgw\Dev\Dto\EndpointResources;
use Woweb\Zgw\Dev\Dto\TypedMapGenerator;
use Woweb\Zgw\Dev\Dto\WriteBuilderGenerator;

$root = dirname(__DIR__);
$generatedDir = $root.'/src/Data/Generated';
$baseNamespace = 'Woweb\\Zgw\\Data\\Generated';

/**
 * Object schemas kept as raw arrays rather than generated DTOs.
 *
 * @var list<string> $opaque
 */
$opaque = ['Processobject'];

/**
 * Schema names mapped to a hand-written value object: stable, standardised structures (GeoJSON)
 * modelled as value objects rather than generated, spec-driven DTOs.
 *
 * @var array<string, array{type: class-string, cast: class-string}> $valueObjects
 */
$valueObjects = [
    'GeoJSONGeometry' => [
        'type' => GeoJsonGeometry::class,
        'cast' => GeoJsonCast::class,
    ],
];

/**
 * Per discriminated schema, how its polymorphic sub-object is typed. Discriminators are detected
 * from the specs; this only narrows the typed set and chooses the fallback for unmapped values.
 *
 * Rol is fully typed: all five betrokkeneType subtypes get a DTO, and an unknown value resolves to
 * null. ZaakObject has 31 objectType subtypes, most of them niche BGT/BAG geo-objects; only the
 * common administrative ones are typed and every other value keeps its raw objectIdentificatie
 * (fallbackToRaw) rather than being dropped. Widening the ZaakObject set is just adding a value
 * here and regenerating.
 *
 * @var array<string, array{only?: list<string>, fallbackToRaw?: bool}> $discriminators
 */
$discriminators = [
    'ZaakObject' => [
        'only' => [
            'adres',
            'pand',
            'kadastrale_onroerende_zaak',
            'woz_object',
            'openbare_ruimte',
            'woonplaats',
            'natuurlijk_persoon',
            'niet_natuurlijk_persoon',
            'vestiging',
        ],
        'fallbackToRaw' => true,
    ],
];

// Discover the annotated resources and group the root schemas per component.
$resources = EndpointResources::discover($root.'/src/Api/Endpoints', 'Woweb\\Zgw\\Api\\Endpoints');

/** @var array<string, list<string>> $rootsByComponent */
$rootsByComponent = [];
foreach ($resources as $resource) {
    $rootsByComponent[$resource->component][$resource->schema] ??= true;
}

/**
 * Write-capable root schemas per component: those whose endpoint can create, patch or replace, so
 * a write builder is only generated for resources that actually accept a write payload.
 *
 * @var array<string, array<string, true>> $writableRootsByComponent
 */
$writableRootsByComponent = [];
foreach ($resources as $endpoint => $resource) {
    $writeCapable = is_subclass_of($endpoint, CreatesResource::class)
        || is_subclass_of($endpoint, PatchesResource::class)
        || is_subclass_of($endpoint, ReplacesResource::class);

    if ($writeCapable) {
        $writableRootsByComponent[$resource->component][$resource->schema] ??= true;
    }
}

/**
 * Root schema names per component, so a generator can resolve a cross-component external $ref (an
 * expanded zaak referencing the catalogi ZaakType) to the generated DTO in that component.
 *
 * @var array<string, list<string>> $rootSchemasByComponent
 */
$rootSchemasByComponent = [];
foreach ($rootsByComponent as $component => $schemas) {
    $rootSchemasByComponent[$component] = array_keys($schemas);
}

// Clear previously generated output so removed schemas do not linger.
if (is_dir($generatedDir)) {
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($generatedDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
}

$total = 0;

foreach ($rootsByComponent as $component => $schemas) {
    $namespace = $baseNamespace.'\\'.EndpointResources::componentNamespace($component);

    $generator = new DtoGenerator(
        component: $component,
        rootSchemas: array_keys($schemas),
        namespace: $namespace,
        outDir: $generatedDir.'/'.EndpointResources::componentNamespace($component),
        opaque: $opaque,
        valueObjects: $valueObjects,
        discriminators: $discriminators,
        baseNamespace: $baseNamespace,
        rootSchemasByComponent: $rootSchemasByComponent,
    );

    $total += count($generator->generate());
}

// Audit trail: a shared cross-component read with the same shape on every resource, so it gets one
// DTO in its own Audittrail namespace rather than a per-component copy. The schema lives in the
// zaken spec; the typed layer exposes it through TypedEndpoint::audittrail(), not the endpoint map.
$auditGenerator = new DtoGenerator(
    component: 'zaken',
    rootSchemas: ['AuditTrail'],
    namespace: $baseNamespace.'\\Audittrail',
    outDir: $generatedDir.'/Audittrail',
    opaque: $opaque,
    valueObjects: $valueObjects,
    discriminators: $discriminators,
    baseNamespace: $baseNamespace,
    rootSchemasByComponent: $rootSchemasByComponent,
);

$total += count($auditGenerator->generate());

$mapGenerator = new TypedMapGenerator(
    endpointsDir: $root.'/src/Api/Endpoints',
    endpointNamespace: 'Woweb\\Zgw\\Api\\Endpoints',
    dtoNamespace: $baseNamespace,
    outFile: $generatedDir.'/TypedMap.php',
);

$mapGenerator->generate();
$total++;

// Write builders: regenerate the per-component subdirectories under src/Data/Writes, leaving the
// hand-written WriteBuilder base in place.
$writesDir = $root.'/src/Data/Writes';
$writesNamespace = 'Woweb\\Zgw\\Data\\Writes';

foreach (glob($writesDir.'/*', GLOB_ONLYDIR) ?: [] as $componentDir) {
    foreach (glob($componentDir.'/*.php') ?: [] as $file) {
        unlink($file);
    }
    rmdir($componentDir);
}

foreach ($writableRootsByComponent as $component => $schemas) {
    $componentNamespace = EndpointResources::componentNamespace($component);

    $writeGenerator = new WriteBuilderGenerator(
        component: $component,
        rootSchemas: array_keys($schemas),
        namespace: $writesNamespace.'\\'.$componentNamespace,
        outDir: $writesDir.'/'.$componentNamespace,
        enumNamespace: $baseNamespace.'\\'.$componentNamespace.'\\Enums',
    );

    $total += count($writeGenerator->generate());
}

echo "{$total} files generated across ".count($rootsByComponent)." components.\n";
