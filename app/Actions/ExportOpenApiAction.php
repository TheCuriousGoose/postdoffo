<?php

namespace App\Actions;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Models\Collection;
use App\Models\Request;
use Illuminate\Support\Str;

/**
 * Serializes a collection subtree into an OpenAPI 3.0.3 document, alongside
 * ExportCollectionAction's Postman output. Every request becomes one
 * path + method operation; Postman's {{variable}} placeholders become
 * OpenAPI {variable} templating, and a leading placeholder segment (e.g.
 * {{base_url}}/users) becomes a templated server entry rather than a
 * literal path segment, since OpenAPI paths must be host-relative.
 *
 * This is inherently lossier than the Postman export: OpenAPI has no place
 * for pre-request/test scripts, and a path+method pair can only hold one
 * operation, so two requests that resolve to the same endpoint collapse
 * into whichever was serialized last.
 */
class ExportOpenApiAction
{
    /** @var array<string, array<string, mixed>> */
    private array $securitySchemes = [];

    /** @var array<string, true> */
    private array $servers = [];

    /**
     * @return array<string, mixed>
     */
    public function handle(Collection $collection): array
    {
        $this->securitySchemes = [];
        $this->servers = [];

        $paths = [];
        $this->collectPaths($collection, $paths);

        $document = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => $collection->name,
                'version' => '1.0.0',
            ],
            'paths' => $paths,
        ];

        if ($this->servers !== []) {
            $document['servers'] = array_map(
                fn (string $url) => $this->serverEntry($url),
                array_keys($this->servers)
            );
        }

        if ($this->securitySchemes !== []) {
            $document['components'] = ['securitySchemes' => $this->securitySchemes];
        }

        return $document;
    }

    /**
     * @param  array<string, array<string, mixed>>  $paths
     */
    private function collectPaths(Collection $collection, array &$paths): void
    {
        foreach ($collection->requests as $request) {
            $this->addOperation($request, $paths);
        }

        foreach ($collection->children as $child) {
            $this->collectPaths($child, $paths);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $paths
     */
    private function addOperation(Request $request, array &$paths): void
    {
        [$server, $path] = $this->splitUrl($request->url);

        if ($server !== null) {
            $this->servers[$server] = true;
        }

        $parameters = array_merge(
            $this->pathParameters($path),
            $this->queryParameters($request->query_params),
            $this->headerParameters($request->headers),
        );

        $operation = [
            'summary' => $request->name,
            'operationId' => $this->operationId($request),
            'responses' => [
                '200' => ['description' => 'Successful response'],
            ],
        ];

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        if ($body = $this->requestBody($request->body_type, $request->body)) {
            $operation['requestBody'] = $body;
        }

        if (($security = $this->securityFor($request->auth_type, $request->auth)) !== null) {
            $operation['security'] = $security;
        }

        $paths[$path] ??= [];
        $paths[$path][strtolower($request->method->value)] = $operation;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function splitUrl(string $url): array
    {
        // query_params already captures the query string structurally.
        $withoutQuery = explode('?', $url, 2)[0];

        $templated = preg_replace('/\{\{\s*([\w.-]+)\s*}}/', '{$1}', $withoutQuery) ?? $withoutQuery;

        $parsed = parse_url($templated);

        if (is_array($parsed) && isset($parsed['host'])) {
            $scheme = $parsed['scheme'] ?? 'https';
            $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
            $path = $parsed['path'] ?? '';

            return ["{$scheme}://{$parsed['host']}{$port}", $path === '' ? '/' : $path];
        }

        $trimmed = ltrim($templated, '/');

        if (preg_match('#^\{([\w.-]+)}(/.*)?$#', $trimmed, $matches)) {
            return ['{'.$matches[1].'}', ($matches[2] ?? '') === '' ? '/' : $matches[2]];
        }

        return [null, '/'.$trimmed];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pathParameters(string $path): array
    {
        preg_match_all('/\{([\w.-]+)}/', $path, $matches);

        return array_map(fn (string $name) => [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'string'],
        ], array_values(array_unique($matches[1])));
    }

    /**
     * @param  array<int, array{key: string, value: string, enabled?: bool}>|null  $list
     * @return array<int, array<string, mixed>>
     */
    private function queryParameters(?array $list): array
    {
        $params = [];

        foreach ($list ?? [] as $entry) {
            if (! is_array($entry) || ($entry['enabled'] ?? true) === false || ($entry['key'] ?? '') === '') {
                continue;
            }

            $params[] = [
                'name' => $entry['key'],
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'string'],
            ];
        }

        return $params;
    }

    /**
     * @param  array<int, array{key: string, value: string, enabled?: bool}>|null  $list
     * @return array<int, array<string, mixed>>
     */
    private function headerParameters(?array $list): array
    {
        $params = [];

        foreach ($list ?? [] as $entry) {
            if (! is_array($entry) || ($entry['enabled'] ?? true) === false) {
                continue;
            }

            $key = $entry['key'] ?? '';

            // The content type is already declared by requestBody's content map.
            if ($key === '' || strcasecmp($key, 'Content-Type') === 0) {
                continue;
            }

            $params[] = [
                'name' => $key,
                'in' => 'header',
                'required' => false,
                'schema' => ['type' => 'string'],
            ];
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|null
     */
    private function requestBody(BodyType $type, ?array $body): ?array
    {
        $body ??= [];

        return match ($type) {
            BodyType::Json => [
                'content' => [
                    'application/json' => array_filter([
                        'schema' => $this->jsonSchema($body['json'] ?? null),
                        'example' => $body['json'] ?? null,
                    ], fn ($v) => $v !== null),
                ],
            ],
            BodyType::Raw => [
                'content' => [
                    'text/plain' => ['schema' => ['type' => 'string'], 'example' => (string) ($body['raw'] ?? '')],
                ],
            ],
            BodyType::FormData => [
                'content' => [
                    'multipart/form-data' => ['schema' => $this->fieldSchema($body['fields'] ?? [])],
                ],
            ],
            BodyType::UrlEncoded => [
                'content' => [
                    'application/x-www-form-urlencoded' => ['schema' => $this->fieldSchema($body['fields'] ?? [])],
                ],
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonSchema(mixed $value): array
    {
        return match (true) {
            is_array($value) && array_is_list($value) => [
                'type' => 'array',
                'items' => $value === [] ? ['type' => 'string'] : $this->jsonSchema($value[0]),
            ],
            is_array($value) => [
                'type' => 'object',
                'properties' => array_map(fn ($v) => $this->jsonSchema($v), $value),
            ],
            is_int($value) => ['type' => 'integer'],
            is_float($value) => ['type' => 'number'],
            is_bool($value) => ['type' => 'boolean'],
            default => ['type' => 'string'],
        };
    }

    /**
     * @param  array<int, array{key: string, value: string, enabled?: bool}>  $fields
     * @return array<string, mixed>
     */
    private function fieldSchema(array $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            if (is_array($field) && ($field['key'] ?? '') !== '') {
                $properties[$field['key']] = ['type' => 'string'];
            }
        }

        return ['type' => 'object', 'properties' => $properties];
    }

    /**
     * @param  array<string, mixed>|null  $auth
     * @return array<int, array<string, array<int, string>>>|null
     */
    private function securityFor(?AuthType $type, ?array $auth): ?array
    {
        $auth ??= [];

        return match ($type) {
            AuthType::None => [],
            AuthType::Bearer => $this->securityRequirement('bearerAuth', ['type' => 'http', 'scheme' => 'bearer']),
            AuthType::Basic => $this->securityRequirement('basicAuth', ['type' => 'http', 'scheme' => 'basic']),
            AuthType::ApiKey => $this->apiKeySecurity($auth),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $scheme
     * @return array<int, array<string, array<int, string>>>
     */
    private function securityRequirement(string $name, array $scheme): array
    {
        $this->securitySchemes[$name] ??= $scheme;

        return [[$name => []]];
    }

    /**
     * @param  array<string, mixed>  $auth
     * @return array<int, array<string, array<int, string>>>
     */
    private function apiKeySecurity(array $auth): array
    {
        $keyName = (string) ($auth['key'] ?? 'apiKey');
        $in = ($auth['in'] ?? 'header') === 'query' ? 'query' : 'header';
        $safeKey = Str::studly(preg_replace('/[^A-Za-z0-9]+/', ' ', $keyName) ?: 'Key');
        $schemeName = 'apiKey'.$safeKey.ucfirst($in);

        return $this->securityRequirement($schemeName, ['type' => 'apiKey', 'name' => $keyName, 'in' => $in]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serverEntry(string $url): array
    {
        if (preg_match('/^\{([\w.-]+)}$/', $url, $matches)) {
            return [
                'url' => $url,
                'variables' => [
                    $matches[1] => ['default' => ''],
                ],
            ];
        }

        return ['url' => $url];
    }

    private function operationId(Request $request): string
    {
        return Str::slug($request->method->value.' '.$request->name).'-'.$request->id;
    }
}
