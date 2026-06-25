<?php

declare(strict_types=1);

namespace Woweb\Zgw\Tests\Contract\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * A thin, dependency-light view over a single ZGW OpenAPI 3 document.
 *
 * The ZGW specs use external $refs with relative file paths (for example the Zaken spec points at
 * the Catalogi spec on disk), so a full object model that eagerly resolves every reference is not
 * usable from a flat fixture layout. This model parses the YAML into an array and resolves only
 * in-file ("#/...") references, which is all the contract assertions need: operation existence,
 * query parameters, and the pagination envelope. External references are treated as opaque.
 */
final class SpecModel
{
    private const HTTP_METHODS = ['get', 'put', 'post', 'delete', 'patch', 'options', 'head', 'trace'];

    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromFile(string $path): self
    {
        return new self(self::parseFile($path));
    }

    /**
     * Parse a spec file into an array.
     *
     * The VNG specs contain double-quoted multiline scalars that the strict pure-PHP symfony/yaml
     * parser rejects, so libyaml (ext-yaml) is preferred when available and symfony/yaml is the
     * fallback. CI enables ext-yaml; locally without it, the few unparseable specs are skipped.
     *
     * @return array<string, mixed>
     */
    private static function parseFile(string $path): array
    {
        if (function_exists('yaml_parse_file')) {
            $data = @yaml_parse_file($path);

            if (is_array($data)) {
                return $data;
            }
        }

        /** @var array<string, mixed> $data */
        $data = Yaml::parseFile($path);

        return $data;
    }

    /**
     * Every (method, path) operation defined in the document.
     *
     * @return list<array{method: string, path: string}>
     */
    public function operations(): array
    {
        $operations = [];

        foreach ($this->paths() as $path => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                if (isset($item[$method])) {
                    $operations[] = ['method' => $method, 'path' => $path];
                }
            }
        }

        return $operations;
    }

    /**
     * Find the spec path template matching a concrete request path for the given method.
     *
     * Path parameters are matched positionally: any "{...}" template segment matches a single
     * concrete segment. Returns the matching template, or null when no operation matches.
     */
    public function matchOperation(string $method, string $concretePath): ?string
    {
        $method = strtolower($method);
        $concreteSegments = $this->segments($concretePath);

        foreach ($this->paths() as $template => $item) {
            if (! is_array($item) || ! isset($item[$method])) {
                continue;
            }

            if ($this->segmentsMatch($this->segments((string) $template), $concreteSegments)) {
                return (string) $template;
            }
        }

        return null;
    }

    /**
     * Names of the query/path parameters declared on an operation (in-file $ref params resolved).
     *
     * @return list<string>
     */
    public function parameterNames(string $path, string $method): array
    {
        $operation = $this->operation($path, $method);
        $parameters = $operation['parameters'] ?? [];

        if (! is_array($parameters)) {
            return [];
        }

        $names = [];

        foreach ($parameters as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            if (isset($parameter['$ref'])) {
                $resolved = $this->resolveInternalRef((string) $parameter['$ref']);
                $parameter = is_array($resolved) ? $resolved : [];
            }

            if (isset($parameter['name'])) {
                $names[] = (string) $parameter['name'];
            }
        }

        return $names;
    }

    /**
     * Property names of the success (2xx) response body schema for an operation.
     *
     * Returns null when there is no JSON success response or the schema sits behind an external
     * reference that cannot be resolved from the local fixture.
     *
     * @return list<string>|null
     */
    public function successResponseProperties(string $path, string $method): ?array
    {
        $operation = $this->operation($path, $method);
        $responses = $operation['responses'] ?? [];

        if (! is_array($responses)) {
            return null;
        }

        $response = null;
        foreach (['200', '201', '202'] as $status) {
            if (isset($responses[$status]) && is_array($responses[$status])) {
                $response = $responses[$status];
                break;
            }
        }

        if ($response === null) {
            return null;
        }

        $content = $response['content'] ?? [];
        if (! is_array($content)) {
            return null;
        }

        $media = $content['application/json'] ?? (is_array(reset($content)) ? reset($content) : null);
        if (! is_array($media) || ! isset($media['schema']) || ! is_array($media['schema'])) {
            return null;
        }

        $properties = $this->schemaProperties($media['schema']);

        return $properties === [] ? null : $properties;
    }

    /**
     * Collect property names from a schema, resolving in-file $refs and merging allOf members.
     *
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public function schemaProperties(array $schema, int $depth = 0): array
    {
        if ($depth > 12) {
            return [];
        }

        if (isset($schema['$ref'])) {
            $resolved = $this->resolveInternalRef((string) $schema['$ref']);

            return is_array($resolved) ? $this->schemaProperties($resolved, $depth + 1) : [];
        }

        $properties = [];

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach (array_keys($schema['properties']) as $name) {
                $properties[] = (string) $name;
            }
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $member) {
                if (is_array($member)) {
                    $properties = array_merge($properties, $this->schemaProperties($member, $depth + 1));
                }
            }
        }

        return array_values(array_unique($properties));
    }

    /**
     * The raw component schemas map (components.schemas), unresolved.
     *
     * @return array<string, mixed>
     */
    public function componentSchemas(): array
    {
        $components = $this->data['components'] ?? [];
        $schemas = is_array($components) ? ($components['schemas'] ?? []) : [];

        return is_array($schemas) ? $schemas : [];
    }

    /**
     * A single component schema by name, or null when it is absent.
     *
     * @return array<string, mixed>|null
     */
    public function componentSchema(string $name): ?array
    {
        $schema = $this->componentSchemas()[$name] ?? null;

        return is_array($schema) ? $schema : null;
    }

    /**
     * Resolve an in-file reference ("#/components/...") to its target, or null when external/missing.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $ref): ?array
    {
        return $this->resolveInternalRef($ref);
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(string $path, string $method): array
    {
        $item = $this->paths()[$path] ?? [];
        $operation = is_array($item) ? ($item[strtolower($method)] ?? []) : [];

        return is_array($operation) ? $operation : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function paths(): array
    {
        $paths = $this->data['paths'] ?? [];

        return is_array($paths) ? $paths : [];
    }

    /**
     * Resolve an in-file JSON pointer ("#/components/...") to its target. Returns null for
     * external references (anything with a file path before the fragment) or missing targets.
     *
     * @return array<string, mixed>|null
     */
    private function resolveInternalRef(string $ref): ?array
    {
        if (! str_starts_with($ref, '#/')) {
            return null;
        }

        $cursor = $this->data;
        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return is_array($cursor) ? $cursor : null;
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', $path), static fn ($s) => $s !== ''));
    }

    /**
     * @param  list<string>  $template
     * @param  list<string>  $concrete
     */
    private function segmentsMatch(array $template, array $concrete): bool
    {
        if (count($template) !== count($concrete)) {
            return false;
        }

        foreach ($template as $index => $segment) {
            $isPlaceholder = str_starts_with($segment, '{') && str_ends_with($segment, '}');

            if (! $isPlaceholder && $segment !== $concrete[$index]) {
                return false;
            }
        }

        return true;
    }
}
