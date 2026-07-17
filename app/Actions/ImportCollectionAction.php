<?php

namespace App\Actions;

use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\Collection;
use App\Models\Workspace;

/**
 * Imports a Postman v2.1 collection export (info/item/variable tree) into
 * workspace collections and requests. Pre-request/test scripts are copied
 * over verbatim as best-effort — Postman's full JS surface is far larger
 * than our sandboxed pm.* subset, so some imported scripts may need manual
 * rewriting before they'll run against ScriptRunner.
 */
class ImportCollectionAction
{
    /**
     * @param  array<string, mixed>  $postmanCollection
     */
    public function handle(Workspace $workspace, array $postmanCollection, ?Collection $parent = null): Collection
    {
        $name = $postmanCollection['info']['name'] ?? 'Imported Collection';

        $collection = $workspace->collections()->create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'variables' => $this->mapVariables($postmanCollection['variable'] ?? []),
            'order' => $workspace->collections()->where('parent_id', $parent?->id)->count(),
        ]);

        $this->importItems($workspace, $collection, $postmanCollection['item'] ?? []);

        return $collection;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function importItems(Workspace $workspace, Collection $collection, array $items): void
    {
        $requestOrder = 0;

        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                $folder = $workspace->collections()->create([
                    'name' => $item['name'] ?? 'Folder',
                    'parent_id' => $collection->id,
                    'variables' => $this->mapVariables($item['variable'] ?? []),
                    'order' => $workspace->collections()->where('parent_id', $collection->id)->count(),
                ]);

                $this->importItems($workspace, $folder, $item['item']);

                continue;
            }

            if (isset($item['request'])) {
                $this->importRequest($collection, $item, $requestOrder++);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function importRequest(Collection $collection, array $item, int $order): void
    {
        $request = $item['request'];
        $method = HttpMethod::tryFrom(strtoupper($request['method'] ?? 'GET')) ?? HttpMethod::Get;

        [$preScript, $testScript] = $this->extractScripts($item['event'] ?? []);
        [$bodyType, $body] = $this->mapBody($request['body'] ?? null);

        $collection->requests()->create([
            'name' => $item['name'] ?? 'Imported Request',
            'method' => $method,
            'url' => $this->extractUrl($request['url'] ?? ''),
            'order' => $order,
            'headers' => $this->mapHeaders($request['header'] ?? []),
            'query_params' => $this->mapQueryParams($request['url'] ?? null),
            'body' => $body,
            'body_type' => $bodyType,
            'pre_request_script' => $preScript,
            'test_script' => $testScript,
        ]);
    }

    private function extractUrl(mixed $url): string
    {
        if (is_string($url)) {
            return $url;
        }

        return $url['raw'] ?? '';
    }

    /**
     * @return array<string, string>|null
     */
    private function mapVariables(mixed $variables): ?array
    {
        if (! is_array($variables) || $variables === []) {
            return null;
        }

        $map = [];

        foreach ($variables as $variable) {
            $key = $variable['key'] ?? null;

            if (is_string($key) && $key !== '') {
                $map[$key] = (string) ($variable['value'] ?? '');
            }
        }

        return $map === [] ? null : $map;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function mapHeaders(array $headers): array
    {
        return array_values(array_map(fn (array $h) => [
            'key' => $h['key'] ?? '',
            'value' => $h['value'] ?? '',
            'enabled' => ! ($h['disabled'] ?? false),
        ], array_filter($headers, fn ($h) => is_array($h))));
    }

    /**
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function mapQueryParams(mixed $url): array
    {
        if (! is_array($url) || ! isset($url['query']) || ! is_array($url['query'])) {
            return [];
        }

        return array_values(array_map(fn (array $q) => [
            'key' => $q['key'] ?? '',
            'value' => $q['value'] ?? '',
            'enabled' => ! ($q['disabled'] ?? false),
        ], array_filter($url['query'], fn ($q) => is_array($q))));
    }

    /**
     * @return array{0: BodyType, 1: array<string, mixed>|null}
     */
    private function mapBody(mixed $body): array
    {
        if (! is_array($body) || ! isset($body['mode'])) {
            return [BodyType::None, null];
        }

        return match ($body['mode']) {
            'raw' => $this->mapRawBody($body),
            'urlencoded' => [BodyType::UrlEncoded, ['fields' => $this->mapFields($body['urlencoded'] ?? [])]],
            'formdata' => [BodyType::FormData, ['fields' => $this->mapFields($body['formdata'] ?? [])]],
            default => [BodyType::None, null],
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: BodyType, 1: array<string, mixed>}
     */
    private function mapRawBody(array $body): array
    {
        $raw = $body['raw'] ?? '';
        $language = $body['options']['raw']['language'] ?? null;

        if ($language === 'json') {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [BodyType::Json, ['json' => $decoded]];
            }
        }

        return [BodyType::Raw, ['raw' => $raw]];
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function mapFields(array $fields): array
    {
        return array_values(array_map(fn (array $f) => [
            'key' => $f['key'] ?? '',
            'value' => $f['value'] ?? '',
            'enabled' => ! ($f['disabled'] ?? false),
        ], array_filter($fields, fn ($f) => is_array($f))));
    }

    /**
     * @param  array<int, mixed>  $events
     * @return array{0: string|null, 1: string|null}
     */
    private function extractScripts(array $events): array
    {
        $pre = null;
        $test = null;

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $exec = $event['script']['exec'] ?? [];
            $script = is_array($exec) ? implode("\n", $exec) : (string) $exec;

            if ($script === '') {
                continue;
            }

            if (($event['listen'] ?? null) === 'prerequest') {
                $pre = $script;
            } elseif (($event['listen'] ?? null) === 'test') {
                $test = $script;
            }
        }

        return [$pre, $test];
    }
}
