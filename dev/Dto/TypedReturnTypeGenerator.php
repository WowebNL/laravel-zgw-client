<?php

declare(strict_types=1);

namespace Woweb\Zgw\Dev\Dto;

use RuntimeException;

/**
 * Generates the conditional return type on Typed::wrap() from the #[ZgwResource] attributes.
 *
 * Typed::wrap() returns a TypedEndpoint, but the concrete DTO it yields depends on which endpoint
 * was passed. A generated @phpstan-return maps each endpoint type to TypedEndpoint<ConcreteDto> so
 * a caller of show()/store()/index() gets the concrete DTO statically, with no runtime change and
 * no configuration on the consumer side (the type travels in the published docblock). The mapping
 * is generated from the same source as TypedMap, so it can never drift from the DTOs.
 *
 * Only the @phpstan-return block between the two markers in Typed.php is rewritten; the rest of the
 * file is hand-maintained.
 */
final class TypedReturnTypeGenerator
{
    private const START_MARKER = '@generated-wrap-return-start';

    private const END_MARKER = '@generated-wrap-return-end';

    private const DATA_CLASS = 'Woweb\\Zgw\\Data\\Data';

    /**
     * @param  string  $endpointsDir  Directory holding the endpoint classes.
     * @param  string  $endpointNamespace  Base namespace of those endpoints.
     * @param  string  $dtoNamespace  Base namespace the generated DTOs live in.
     * @param  string  $typedFile  Path of the Typed.php file to rewrite.
     */
    public function __construct(
        private readonly string $endpointsDir,
        private readonly string $endpointNamespace,
        private readonly string $dtoNamespace,
        private readonly string $typedFile,
    ) {}

    public function generate(): string
    {
        $map = [];
        foreach (EndpointResources::discover($this->endpointsDir, $this->endpointNamespace) as $endpoint => $resource) {
            $map[$endpoint] = EndpointResources::dtoClass($this->dtoNamespace, $resource);
        }

        $contents = file_get_contents($this->typedFile);
        if ($contents === false) {
            throw new RuntimeException("Could not read [{$this->typedFile}].");
        }

        $lines = explode("\n", $contents);
        $start = $end = null;
        foreach ($lines as $i => $line) {
            if (str_contains($line, self::START_MARKER)) {
                $start = $i;
            } elseif (str_contains($line, self::END_MARKER)) {
                $end = $i;
                break;
            }
        }

        if ($start === null || $end === null || $end <= $start) {
            throw new RuntimeException(
                "Could not find the generated-return markers in [{$this->typedFile}]."
            );
        }

        $replaced = array_merge(
            array_slice($lines, 0, $start + 1),
            $this->renderReturn($map),
            array_slice($lines, $end),
        );

        file_put_contents($this->typedFile, implode("\n", $replaced));

        return $this->typedFile;
    }

    /**
     * Render the @phpstan-return docblock lines for the endpoint to DTO map.
     *
     * @param  array<class-string, string>  $map
     * @return list<string>
     */
    private function renderReturn(array $map): array
    {
        // Build the nested conditional from the inside out so the parentheses always balance:
        // ($endpoint is E1 ? TypedEndpoint<D1> : ($endpoint is E2 ? ... : TypedEndpoint<Data>)).
        $acc = 'TypedEndpoint<\\'.self::DATA_CLASS.'>';
        foreach (array_reverse($map, true) as $endpoint => $dto) {
            $acc = "(\$endpoint is \\{$endpoint} ? TypedEndpoint<\\{$dto}> : {$acc})";
        }

        $lines = ['     * @phpstan-return '.$acc];

        return $lines;
    }
}
