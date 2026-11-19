<?php

namespace App\Support;

/**
 * Checks a Teams app manifest against Microsoft's published schema before the
 * package is built.
 *
 * Teams validates the manifest only at upload time and reports one violation
 * at a time, so without this a mistake costs a full build-upload-read-error
 * round trip for each problem. The schema is vendored alongside the template
 * (resources/teams/manifest.schema.json) so this never depends on the network.
 *
 * This covers the rules that actually fail an upload — properties the schema
 * does not allow, missing required ones, enums, patterns and length limits —
 * rather than being a complete JSON Schema implementation.
 */
class TeamsManifestValidator
{
    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string> Human-readable violations; empty when valid.
     */
    public function violations(array $manifest): array
    {
        $schema = json_decode(
            (string) file_get_contents(resource_path('teams/manifest.schema.json')),
            true,
        );

        $violations = [];

        $this->check($manifest, $schema, $schema, 'manifest', $violations);

        return $violations;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $root
     * @param  list<string>  $violations
     */
    private function check(mixed $value, array $schema, array $root, string $path, array &$violations): void
    {
        $schema = $this->resolve($schema, $root);

        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $violations[] = $path.': '.json_encode($value).' is not one of '.json_encode($schema['enum']);
        }

        if (is_string($value)) {
            $this->checkString($value, $schema, $path, $violations);
        }

        if (! is_array($value)) {
            return;
        }

        if (isset($schema['properties'])) {
            $this->checkObject($value, $schema, $root, $path, $violations);
        }

        if (isset($schema['items']) && array_is_list($value)) {
            if (isset($schema['maxItems']) && count($value) > $schema['maxItems']) {
                $violations[] = $path.': '.count($value).' items exceeds the maximum of '.$schema['maxItems'];
            }

            foreach ($value as $index => $item) {
                $this->check($item, $schema['items'], $root, "{$path}[{$index}]", $violations);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $violations
     */
    private function checkString(string $value, array $schema, string $path, array &$violations): void
    {
        if (isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) {
            $violations[] = $path.': '.mb_strlen($value).' characters exceeds the maximum of '.$schema['maxLength'];
        }

        if (isset($schema['pattern']) && preg_match($this->delimit($schema['pattern']), $value) !== 1) {
            $violations[] = "{$path}: '{$value}' does not match {$schema['pattern']}";
        }
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $root
     * @param  list<string>  $violations
     */
    private function checkObject(array $value, array $schema, array $root, string $path, array &$violations): void
    {
        foreach ($schema['required'] ?? [] as $required) {
            if (! array_key_exists($required, $value)) {
                $violations[] = "{$path}: missing required property '{$required}'";
            }
        }

        foreach ($value as $key => $child) {
            if (! isset($schema['properties'][$key])) {
                if (($schema['additionalProperties'] ?? true) === false) {
                    $violations[] = "{$path}.{$key}: the schema does not allow this property";
                }

                continue;
            }

            $this->check($child, $schema['properties'][$key], $root, "{$path}.{$key}", $violations);
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $root
     * @return array<string, mixed>
     */
    private function resolve(array $node, array $root): array
    {
        if (! isset($node['$ref'])) {
            return $node;
        }

        $target = $root;

        foreach (explode('/', ltrim((string) $node['$ref'], '#/')) as $segment) {
            $target = $target[$segment] ?? [];
        }

        return $target;
    }

    /**
     * Wrap a schema pattern in a delimiter the pattern does not itself use —
     * several contain "#", which would otherwise terminate the expression.
     */
    private function delimit(string $pattern): string
    {
        foreach (['~', '#', '%', '!', '@'] as $delimiter) {
            if (! str_contains($pattern, $delimiter)) {
                return $delimiter.$pattern.$delimiter;
            }
        }

        return '/'.str_replace('/', '\/', $pattern).'/';
    }
}
