<?php

declare(strict_types=1);

/**
 * Regenerates the typed read DTOs from the pinned ZGW OpenAPI specs.
 *
 * Run with `composer dto:generate`. Requires the spec fixtures to be present (run
 * `composer contract:fetch` first). The generated files under src/Data/Generated are committed,
 * so the runtime never carries the generator. Review the git diff of that directory after running.
 */

require __DIR__.'/../vendor/autoload.php';

use Woweb\Zgw\Dev\Dto\DtoGenerator;
use Woweb\Zgw\Dev\Dto\TypedMapGenerator;

/** @var list<array{component: string, schema: string, opaque: list<string>}> $resources */
$resources = [
    [
        'component' => 'zaken',
        'schema' => 'Zaak',
        'opaque' => ['GeoJSONGeometry', 'Processobject'],
    ],
];

$root = dirname(__DIR__);
$total = 0;

foreach ($resources as $resource) {
    $generator = new DtoGenerator(
        component: $resource['component'],
        rootSchema: $resource['schema'],
        namespace: 'Woweb\\Zgw\\Data\\Generated',
        outDir: $root.'/src/Data/Generated',
        opaque: $resource['opaque'],
    );

    $paths = $generator->generate();
    $total += count($paths);

    foreach ($paths as $path) {
        echo 'wrote '.substr($path, strlen($root) + 1)."\n";
    }
}

$mapGenerator = new TypedMapGenerator(
    endpointsDir: $root.'/src/Api/Endpoints',
    endpointNamespace: 'Woweb\\Zgw\\Api\\Endpoints',
    dtoNamespace: 'Woweb\\Zgw\\Data\\Generated',
    outFile: $root.'/src/Data/Generated/TypedMap.php',
);

$mapPath = $mapGenerator->generate();
$total++;
echo 'wrote '.substr($mapPath, strlen($root) + 1)."\n";

echo "\n{$total} files generated.\n";
