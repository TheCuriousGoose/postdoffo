<?php

namespace App\Services;

use App\Enums\AuthType;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request;
use App\Models\Workspace;

/**
 * Resolves {{variable}} interpolation with workspace -> collection -> environment
 * -> runtime precedence (later sources override earlier ones). Workspace "globals"
 * are the always-on base layer, applied whatever environment is active. "Runtime"
 * overrides stand in for Postman's per-execution global scope: values a pre-request
 * script sets for the current execution take the highest precedence.
 */
class VariableResolver
{
    private const PATTERN = '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/';

    /**
     * Build the flattened key => value variable map for a request execution.
     * Workspace globals form the base, overridden in turn by the collection
     * chain, the active environment, then any runtime overrides.
     *
     * @param  array<string, string>  $runtimeOverrides
     * @return array<string, string>
     */
    public function resolve(?Collection $collection, ?Environment $environment, array $runtimeOverrides = [], ?Workspace $workspace = null): array
    {
        $variables = [];

        if ($workspace) {
            foreach ($workspace->variables as $variable) {
                $variables[$variable->key] = $variable->value ?? '';
            }
        }

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
     * Flatten the default headers set on a collection and its ancestors —
     * root-first, so a folder's own header overrides one inherited from its
     * parent. Disabled entries and blank keys are dropped, mirroring how
     * request headers are read in RequestExecutorService.
     *
     * @return array<string, string>
     */
    public function resolveHeaders(?Collection $collection): array
    {
        $headers = [];

        foreach ($this->collectionChain($collection) as $ancestor) {
            foreach ($ancestor->headers ?? [] as $header) {
                if (($header['enabled'] ?? true) === false || $header['key'] === '') {
                    continue;
                }

                $headers[$header['key']] = $header['value'];
            }
        }

        return $headers;
    }

    /**
     * Resolve the Authorization a request should send: nearest-defined-wins
     * down the collection chain (a folder's own auth overrides its parent's,
     * an explicit "none" stops inheritance), then the request's own auth — if
     * set — has final say. Credentials are interpolated against the already-
     * resolved variable map before Basic auth is base64-encoded, so
     * `{{username}}`/`{{password}}` produce a correct digest — unlike a
     * plain header string, this is computed fresh on every send rather than
     * baked in at import time.
     *
     * @param  array<string, string>  $variables
     * @return array{location: 'header'|'query', key: string, value: string}|null
     */
    public function resolveAuth(Request $request, array $variables): ?array
    {
        $type = null;
        $fields = [];

        foreach ($this->collectionChain($request->collection) as $ancestor) {
            if ($ancestor->auth_type !== null) {
                $type = $ancestor->auth_type;
                $fields = $ancestor->auth ?? [];
            }
        }

        if ($request->auth_type !== null) {
            $type = $request->auth_type;
            $fields = $request->auth ?? [];
        }

        return match ($type) {
            AuthType::Bearer => [
                'location' => 'header',
                'key' => 'Authorization',
                'value' => 'Bearer '.$this->interpolate($fields['token'] ?? '', $variables),
            ],
            AuthType::Basic => [
                'location' => 'header',
                'key' => 'Authorization',
                'value' => 'Basic '.base64_encode(
                    $this->interpolate($fields['username'] ?? '', $variables).
                    ':'.
                    $this->interpolate($fields['password'] ?? '', $variables)
                ),
            ],
            AuthType::ApiKey => [
                'location' => ($fields['in'] ?? 'header') === 'query' ? 'query' : 'header',
                'key' => $this->interpolate($fields['key'] ?? '', $variables),
                'value' => $this->interpolate($fields['value'] ?? '', $variables),
            ],
            default => null,
        };
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
