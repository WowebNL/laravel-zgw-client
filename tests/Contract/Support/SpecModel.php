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
     * The writable property names of a schema: those not marked readOnly, resolving in-file $refs
     * and merging allOf members. These are the fields a create or update payload may carry.
     *
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public function writablePropertyNames(array $schema, int $depth = 0): array
    {
        if ($depth > 12) {
            return [];
        }

        if (isset($schema['$ref'])) {
            $resolved = $this->resolveInternalRef((string) $schema['$ref']);

            return is_array($resolved) ? $this->writablePropertyNames($resolved, $depth + 1) : [];
        }

        $properties = [];

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $name => $sub) {
                if (is_array($sub) && ($sub['readOnly'] ?? false) !== true) {
                    $properties[] = (string) $name;
                }
            }
        }

        if (isset($schema['allOf']) && is_array($schema['allOf'])) {
            foreach ($schema['allOf'] as $member) {
                if (is_array($member)) {
                    $properties = array_merge($properties, $this->writablePropertyNames($member, $depth + 1));
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
     * The embedded schema name an expandable resource exposes through its `_expand` field, or null
     * when the resource has no expanded variant in this release.
     *
     * ZGW models expansion (`?expand=`) as a sibling schema `{Schema}Expanded` shaped allOf[Schema,
     * {properties: {_expand: {Schema}Embedded}}]. The embedded schema carries the expanded related
     * resources. This finds that embedded schema name so the `_expand` field can be typed and so the
     * contract suite knows `_expand` is a real field of the base DTO.
     */
    public function expandResolution(string $schemaName): ?string
    {
        $expanded = $this->componentSchema($schemaName.'Expanded');

        if ($expanded === null) {
            return null;
        }

        // A plain resource carries _expand on an allOf member (allOf[Schema, {_expand}]); a
        // discriminated resource (Rol, ZaakObject) carries it directly in its own properties.
        $candidates = [];
        if (isset($expanded['properties']) && is_array($expanded['properties'])) {
            $candidates[] = $expanded['properties'];
        }
        foreach (is_array($expanded['allOf'] ?? null) ? $expanded['allOf'] : [] as $member) {
            if (is_array($member) && isset($member['properties']) && is_array($member['properties'])) {
                $candidates[] = $member['properties'];
            }
        }

        foreach ($candidates as $properties) {
            $expand = $properties['_expand'] ?? null;

            if (is_array($expand) && isset($expand['$ref']) && is_string($expand['$ref'])) {
                return $this->basename($expand['$ref']);
            }
        }

        return null;
    }

    /**
     * Resolve a schema's discriminator into the polymorphic sub-object it selects.
     *
     * ZGW models polymorphism as a base schema carrying a `discriminator` whose `propertyName` is a
     * sibling enum field, plus a `mapping` of each value to a subtype shaped allOf[Base,
     * {x}_identificatie]. That extra allOf member holds a single property (for example
     * betrokkeneIdentificatie or objectIdentificatie) pointing at the value's leaf schema (for
     * example RolMedewerker or ObjectAdres). This returns the discriminator property name, that
     * single polymorphic field name, and the map of discriminator value to leaf schema name.
     *
     * Values whose subtype adds no identificatie member (the object is then carried by a URL field)
     * are omitted from the map. Returns null when the schema has no usable discriminator.
     *
     * @return array{property: string, field: string, map: array<string, string>}|null
     */
    public function discriminatorResolution(string $schemaName): ?array
    {
        $schema = $this->componentSchema($schemaName);

        if ($schema === null || ! isset($schema['discriminator']) || ! is_array($schema['discriminator'])) {
            return null;
        }

        $discriminator = $schema['discriminator'];
        $property = isset($discriminator['propertyName']) ? (string) $discriminator['propertyName'] : null;
        $mapping = isset($discriminator['mapping']) && is_array($discriminator['mapping']) ? $discriminator['mapping'] : [];

        if ($property === null || $mapping === []) {
            return null;
        }

        $field = null;
        $map = [];

        foreach ($mapping as $value => $ref) {
            if (! is_string($ref)) {
                continue;
            }

            $subtype = $this->resolveInternalRef($ref);
            $members = is_array($subtype['allOf'] ?? null) ? $subtype['allOf'] : [];

            foreach ($members as $member) {
                if (! is_array($member) || ! isset($member['$ref']) || ! is_string($member['$ref'])) {
                    continue;
                }

                if ($this->basename($member['$ref']) === $schemaName) {
                    continue; // The base schema; the subtype adds only the identificatie member.
                }

                $memberSchema = $this->resolveInternalRef($member['$ref']);
                $properties = is_array($memberSchema['properties'] ?? null) ? $memberSchema['properties'] : [];

                foreach ($properties as $name => $sub) {
                    if (! is_array($sub)) {
                        continue;
                    }

                    $field = (string) $name;
                    $leaf = $this->leafRef($sub);

                    if ($leaf !== null) {
                        $map[(string) $value] = $leaf;
                    }
                }
            }
        }

        if ($field === null || $map === []) {
            return null;
        }

        return ['property' => $property, 'field' => $field, 'map' => $map];
    }

    /**
     * The leaf schema name a property points at, unwrapping a direct $ref or a single-member
     * allOf/oneOf/anyOf, or null when the property is not a reference.
     *
     * @param  array<string, mixed>  $schema
     */
    private function leafRef(array $schema): ?string
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            return $this->basename($schema['$ref']);
        }

        foreach (['allOf', 'oneOf', 'anyOf'] as $combiner) {
            if (! isset($schema[$combiner]) || ! is_array($schema[$combiner])) {
                continue;
            }

            foreach ($schema[$combiner] as $member) {
                if (is_array($member) && isset($member['$ref']) && is_string($member['$ref'])) {
                    return $this->basename($member['$ref']);
                }
            }
        }

        return null;
    }

    private function basename(string $ref): string
    {
        $parts = explode('/', $ref);

        return (string) end($parts);
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
