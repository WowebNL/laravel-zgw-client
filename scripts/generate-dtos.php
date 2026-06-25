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

use Woweb\Zgw\Data\Casts\GeoJsonCast;
use Woweb\Zgw\Data\Values\GeoJsonGeometry;
use Woweb\Zgw\Dev\Dto\DtoGenerator;
use Woweb\Zgw\Dev\Dto\EndpointResources;
use Woweb\Zgw\Dev\Dto\TypedMapGenerator;

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

// Discover the annotated resources and group the root schemas per component.
$resources = EndpointResources::discover($root.'/src/Api/Endpoints', 'Woweb\\Zgw\\Api\\Endpoints');

/** @var array<string, list<string>> $rootsByComponent */
$rootsByComponent = [];
foreach ($resources as $resource) {
    $rootsByComponent[$resource->component][$resource->schema] ??= true;
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
    );

    $total += count($generator->generate());
}

$mapGenerator = new TypedMapGenerator(
    endpointsDir: $root.'/src/Api/Endpoints',
    endpointNamespace: 'Woweb\\Zgw\\Api\\Endpoints',
    dtoNamespace: $baseNamespace,
    outFile: $generatedDir.'/TypedMap.php',
);

$mapGenerator->generate();
$total++;

echo "{$total} files generated across ".count($rootsByComponent)." components.\n";
