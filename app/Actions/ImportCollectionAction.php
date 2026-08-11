<?php

namespace App\Actions;

use App\Enums\AuthType;
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
 *
 * Collection/folder/request-level `auth` is stored as structured auth_type +
 * auth fields (see mapAuth()) rather than a pre-baked header — an explicit
 * "noauth" maps to AuthType::None, which correctly stops inheritance the
 * same way it does in Postman. VariableResolver::resolveAuth() walks the
 * collection chain and computes the real header (or query param) at send
 * time, after variable interpolation — so Basic auth credentials containing
 * `{{variables}}` are encoded correctly, unlike a header baked in at import.
 * Schemes we don't model (oauth2, digest, awsv4, hawk, ntlm, edgegrid) are
 * skipped, same as scripts outside our pm.* subset.
 */
class ImportCollectionAction
{
    /**
     * @param  array<string, mixed>  $postmanCollection
     */
    public function handle(Workspace $workspace, array $postmanCollection, ?Collection $parent = null): Collection
    {
        $name = $postmanCollection['info']['name'] ?? 'Imported Collection';
        [$authType, $auth] = $this->mapAuth($postmanCollection['auth'] ?? null);
        $variables = $this->mapVariables($postmanCollection['variable'] ?? []);

        $collection = $workspace->collections()->create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'variables' => $variables,
            'auth_type' => $authType,
            'auth' => $auth,
            'order' => $workspace->collections()->where('parent_id', $parent?->id)->count(),
        ]);

        $this->importItems($workspace, $collection, $postmanCollection['item'] ?? []);

        // A top-level import seeds an environment from the collection's base
        // variables, so the imported {{placeholders}} resolve to something the
        // moment the collection lands — and give the user an obvious, editable
        // place to swap those values per environment.
        if ($parent === null && $variables !== null) {
            $this->createBaseEnvironment($workspace, $name, $variables);
        }

        return $collection;
    }

    /**
     * Create (and, if the workspace has no active one, activate) an environment
     * holding the collection's base variables. Keys that read like credentials
     * are stored as secret so their values are masked in the UI.
     *
     * @param  array<string, string>  $variables
     */
    private function createBaseEnvironment(Workspace $workspace, string $collectionName, array $variables): void
    {
        $environment = $workspace->environments()->create([
            'name' => $collectionName.' (base)',
            'is_active' => ! $workspace->environments()->where('is_active', true)->exists(),
        ]);

        foreach ($variables as $key => $value) {
            $environment->variables()->create([
                'key' => $key,
                'value' => $value,
                'is_secret' => $this->looksSecret($key),
            ]);
        }
    }

    /**
     * Heuristic: a variable named like a credential (token, secret, password,
     * api key, ...) is stored as secret so its value is masked by default.
     */
    private function looksSecret(string $key): bool
    {
        return (bool) preg_match('/(secret|password|passwd|token|api[_-]?key|apikey|auth|bearer|credential|private[_-]?key)/i', $key);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function importItems(Workspace $workspace, Collection $collection, array $items): void
    {
        $requestOrder = 0;

        foreach ($items as $item) {
            if (isset($item['item']) && is_array($item['item'])) {
                [$authType, $auth] = $this->mapAuth($item['auth'] ?? null);

                $folder = $workspace->collections()->create([
                    'name' => $item['name'] ?? 'Folder',
                    'parent_id' => $collection->id,
                    'variables' => $this->mapVariables($item['variable'] ?? []),
                    'auth_type' => $authType,
                    'auth' => $auth,
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
        [$authType, $auth] = $this->mapAuth($request['auth'] ?? null);

        $collection->requests()->create([
            'name' => $item['name'] ?? 'Imported Request',
            'method' => $method,
            'url' => $this->extractUrl($request['url'] ?? ''),
            'order' => $order,
            'headers' => $this->mapHeaders($request['header'] ?? []),
            'query_params' => $this->mapQueryParams($request['url'] ?? null),
            'body' => $body,
            'body_type' => $bodyType,
            'auth_type' => $authType,
            'auth' => $auth,
            'pre_request_script' => $preScript,
            'test_script' => $testScript,
        ]);
    }

    /**
     * Translate a Postman `auth` block into our (auth_type, auth) pair.
     * Supports bearer, basic, apikey, and explicit noauth (-> AuthType::None,
     * which cancels inheritance). Anything else — including a missing/absent
     * `auth` key, which means "no opinion, inherit from the parent" — yields
     * [null, null].
     *
     * @return array{0: AuthType|null, 1: array<string, mixed>|null}
     */
    private function mapAuth(mixed $auth): array
    {
        if (! is_array($auth) || ! is_string($auth['type'] ?? null)) {
            return [null, null];
        }

        $fields = $this->authFields($auth[$auth['type']] ?? []);

        return match ($auth['type']) {
            'noauth' => [AuthType::None, null],
            'bearer' => isset($fields['token'])
                ? [AuthType::Bearer, ['token' => (string) $fields['token']]]
                : [null, null],
            'basic' => [AuthType::Basic, [
                'username' => (string) ($fields['username'] ?? ''),
                'password' => (string) ($fields['password'] ?? ''),
            ]],
            'apikey' => is_string($fields['key'] ?? null) && $fields['key'] !== ''
                ? [AuthType::ApiKey, [
                    'key' => $fields['key'],
                    'value' => (string) ($fields['value'] ?? ''),
                    'in' => ($fields['in'] ?? 'header') === 'query' ? 'query' : 'header',
                ]]
                : [null, null],
            default => [null, null],
        };
    }

    /**
     * Postman stores each auth scheme's fields as a flat list of {key, value}
     * pairs rather than an object, e.g. `[{"key": "token", "value": "..."}]`.
     *
     * @return array<string, mixed>
     */
    private function authFields(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        $fields = [];

        foreach ($list as $entry) {
            if (is_array($entry) && is_string($entry['key'] ?? null)) {
                $fields[$entry['key']] = $entry['value'] ?? null;
            }
        }

        return $fields;
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
        if (is_array($url) && isset($url['query']) && is_array($url['query'])) {
            $params = array_values(array_map(fn (array $q) => [
                'key' => $q['key'] ?? '',
                'value' => $q['value'] ?? '',
                'enabled' => ! ($q['disabled'] ?? false),
            ], array_filter($url['query'], fn ($q) => is_array($q))));

            if ($params !== []) {
                return $params;
            }
        }

        // Many exports (and hand-written collections) carry the params only in
        // the raw URL itself, with no structured `query` array — parse them out
        // so the Params tab isn't empty when the URL clearly has a query string.
        return $this->parseQueryString($this->extractUrl($url));
    }

    /**
     * Split a raw URL's query string into rows, leaving {{placeholders}} intact
     * (a manual split rather than parse_url, which chokes on `{{baseUrl}}`).
     *
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function parseQueryString(string $url): array
    {
        $start = strpos($url, '?');

        if ($start === false) {
            return [];
        }

        $query = substr($url, $start + 1);
        $fragment = strpos($query, '#');

        if ($fragment !== false) {
            $query = substr($query, 0, $fragment);
        }

        if ($query === '') {
            return [];
        }

        $params = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            $eq = strpos($pair, '=');
            $key = $eq === false ? $pair : substr($pair, 0, $eq);
            $value = $eq === false ? '' : substr($pair, $eq + 1);

            $params[] = [
                'key' => urldecode($key),
                'value' => urldecode($value),
                'enabled' => true,
            ];
        }

        return $params;
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
     * A Postman form-data row can be a file rather than a value, in which case
     * it carries `src` — a path on whichever machine exported the collection,
     * which is no use to us. The row still comes across as a file field naming
     * the file it wants, so it shows up in the editor as one waiting to be
     * re-picked rather than silently arriving as an empty text field.
     *
     * @param  array<int, mixed>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function mapFields(array $fields): array
    {
        return array_values(array_map(function (array $f) {
            $field = [
                'key' => $f['key'] ?? '',
                'value' => is_scalar($f['value'] ?? null) ? (string) $f['value'] : '',
                'enabled' => ! ($f['disabled'] ?? false),
            ];

            if (($f['type'] ?? null) === 'file') {
                $src = is_array($f['src'] ?? null) ? ($f['src'][0] ?? null) : ($f['src'] ?? null);

                $field['value'] = '';
                $field['type'] = 'file';
                $field['file_id'] = null;
                $field['filename'] = is_string($src) && $src !== ''
                    ? basename(str_replace('\\', '/', $src))
                    : null;
            }

            return $field;
        }, array_filter($fields, fn ($f) => is_array($f))));
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
