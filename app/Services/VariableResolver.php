<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Environment;

/**
 * Resolves {{variable}} interpolation with collection -> environment -> runtime
 * precedence (later sources override earlier ones). "Runtime" overrides stand in
 * for Postman's global scope: values a pre-request script sets for the current
 * execution take the highest precedence.
 */
class VariableResolver
{
    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/';

    /**
     * Build the flattened key => value variable map for a request execution.
     *
     * @param  array<string, string>  $runtimeOverrides
     * @return array<string, string>
     */
    public function resolve(?Collection $collection, ?Environment $environment, array $runtimeOverrides = []): array
    {
        $variables = [];

        foreach ($this->collectionChain($collection) as $ancestor) {
            $variables = [...$variables, ...($ancestor->variables ?? [])];
        }

        if ($environment) {
            foreach ($environment->variables as $variable) {
                $variables[$variable->key] = $variable->value;
            }
        }

        return [...$variables, ...$runtimeOverrides];
    }

    /**
     * Replace every {{key}} occurrence in a string. Unresolved variables are left as-is.
     *
     * @param  array<string, string>  $variables
     */
    public function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn (array $match) => $variables[$match[1]] ?? $match[0],
            $template,
        );
    }

    /**
     * Recursively interpolate every string value in an array (headers, query params, body, ...).
     *
     * @param  array<mixed>  $data
     * @param  array<string, string>  $variables
     * @return array<mixed>
     */
    public function interpolateArray(array $data, array $variables): array
    {
        array_walk_recursive($data, function (&$value) use ($variables): void {
            if (is_string($value)) {
                $value = $this->interpolate($value, $variables);
            }
        });

        return $data;
    }

    /**
     * @return array<int, Collection> Root-first, so a nearer collection overrides its ancestors.
     */
    private function collectionChain(?Collection $collection): array
    {
        $chain = [];

        while ($collection) {
            $chain[] = $collection;
            $collection = $collection->parent;
        }

        return array_reverse($chain);
    }
}
