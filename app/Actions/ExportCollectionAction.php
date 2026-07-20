<?php

namespace App\Actions;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Models\Collection;
use App\Models\Request;

/**
 * Serializes a collection subtree back into a Postman v2.1 export, the inverse
 * of ImportCollectionAction. This is how a collection is "shared": the download
 * drops into Postman, Insomnia or another PostDoffo workspace unchanged.
 * Structured auth becomes Postman's flat {key,value} auth blocks, and our
 * pre-request/test scripts become prerequest/test events.
 */
class ExportCollectionAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Collection $collection): array
    {
        $export = [
            'info' => [
                'name' => $collection->name,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => $this->items($collection),
        ];

        if ($auth = $this->authBlock($collection->auth_type, $collection->auth)) {
            $export['auth'] = $auth;
        }

        if ($variables = $this->variableList($collection->variables)) {
            $export['variable'] = $variables;
        }

        return $export;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(Collection $collection): array
    {
        $items = [];

        foreach ($collection->requests as $request) {
            $items[] = $this->requestItem($request);
        }

        foreach ($collection->children as $child) {
            $folder = ['name' => $child->name, 'item' => $this->items($child)];

            if ($auth = $this->authBlock($child->auth_type, $child->auth)) {
                $folder['auth'] = $auth;
            }

            if ($variables = $this->variableList($child->variables)) {
                $folder['variable'] = $variables;
            }

            $items[] = $folder;
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestItem(Request $request): array
    {
        $requestData = [
            'method' => $request->method->value,
            'header' => $this->keyValueList($request->headers),
            'url' => ['raw' => $request->url],
        ];

        if ($body = $this->bodyBlock($request->body_type, $request->body)) {
            $requestData['body'] = $body;
        }

        if ($auth = $this->authBlock($request->auth_type, $request->auth)) {
            $requestData['auth'] = $auth;
        }

        $item = ['name' => $request->name, 'request' => $requestData];

        $events = [];

        if ($request->pre_request_script) {
            $events[] = $this->event('prerequest', $request->pre_request_script);
        }

        if ($request->test_script) {
            $events[] = $this->event('test', $request->test_script);
        }

        if ($events !== []) {
            $item['event'] = $events;
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $listen, string $script): array
    {
        return [
            'listen' => $listen,
            'script' => ['type' => 'text/javascript', 'exec' => explode("\n", $script)],
        ];
    }

    /**
     * @param  array<int, array{key: string, value: string, enabled?: bool}>|null  $list
     * @return array<int, array<string, mixed>>
     */
    private function keyValueList(?array $list): array
    {
        return array_values(array_map(function (array $entry) {
            $mapped = ['key' => $entry['key'] ?? '', 'value' => $entry['value'] ?? ''];

            if (($entry['enabled'] ?? true) === false) {
                $mapped['disabled'] = true;
            }

            return $mapped;
        }, array_filter($list ?? [], fn ($e) => is_array($e))));
    }

    /**
     * @param  array<string, string>|null  $variables
     * @return array<int, array{key: string, value: string}>
     */
    private function variableList(?array $variables): array
    {
        $list = [];

        foreach ($variables ?? [] as $key => $value) {
            $list[] = ['key' => (string) $key, 'value' => (string) $value];
        }

        return $list;
    }

    /**
     * @param  array<string, mixed>|null  $auth
     * @return array<string, mixed>|null
     */
    private function authBlock(?AuthType $type, ?array $auth): ?array
    {
        $auth ??= [];

        return match ($type) {
            AuthType::None => ['type' => 'noauth'],
            AuthType::Bearer => [
                'type' => 'bearer',
                'bearer' => [['key' => 'token', 'value' => (string) ($auth['token'] ?? ''), 'type' => 'string']],
            ],
            AuthType::Basic => [
                'type' => 'basic',
                'basic' => [
                    ['key' => 'username', 'value' => (string) ($auth['username'] ?? ''), 'type' => 'string'],
                    ['key' => 'password', 'value' => (string) ($auth['password'] ?? ''), 'type' => 'string'],
                ],
            ],
            AuthType::ApiKey => [
                'type' => 'apikey',
                'apikey' => [
                    ['key' => 'key', 'value' => (string) ($auth['key'] ?? ''), 'type' => 'string'],
                    ['key' => 'value', 'value' => (string) ($auth['value'] ?? ''), 'type' => 'string'],
                    ['key' => 'in', 'value' => (string) ($auth['in'] ?? 'header'), 'type' => 'string'],
                ],
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>|null
     */
    private function bodyBlock(BodyType $type, ?array $body): ?array
    {
        $body ??= [];

        return match ($type) {
            BodyType::Raw => ['mode' => 'raw', 'raw' => (string) ($body['raw'] ?? '')],
            BodyType::Json => [
                'mode' => 'raw',
                'raw' => json_encode($body['json'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'options' => ['raw' => ['language' => 'json']],
            ],
            BodyType::FormData => ['mode' => 'formdata', 'formdata' => $this->keyValueList($body['fields'] ?? [])],
            BodyType::UrlEncoded => ['mode' => 'urlencoded', 'urlencoded' => $this->keyValueList($body['fields'] ?? [])],
            default => null,
        };
    }
}
