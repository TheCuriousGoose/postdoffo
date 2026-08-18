<?php

namespace App\Mcp;

use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request as ApiRequest;
use App\Models\RequestHistory;
use App\Models\User;
use App\Models\Workspace;

/**
 * One place deciding what an MCP tool hands back for each model.
 *
 * Tool output is context the model pays for on every turn, so these shapes are
 * deliberately narrower than the API's: no timestamps nobody reasons about, no
 * response bodies repeated inside a listing. Keeping them here also means two
 * tools returning "a request" return the same keys, which is what lets the model
 * chain calls without re-reading the shape each time.
 */
final class Presenter
{
    /**
     * @return array<string, mixed>
     */
    public static function workspace(Workspace $workspace, User $user): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'your_role' => $workspace->roleFor($user)?->value,
            'collections_count' => $workspace->collections_count ?? $workspace->collections()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function collection(Collection $collection): array
    {
        return [
            'id' => $collection->id,
            'workspace_id' => $collection->workspace_id,
            'parent_id' => $collection->parent_id,
            'name' => $collection->name,
            'order' => $collection->order,
            'variables' => $collection->variables,
            'headers' => $collection->headers,
            'auth_type' => $collection->auth_type?->value,
            'auth' => self::redactAuth($collection->auth),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function request(ApiRequest $apiRequest): array
    {
        return [
            'id' => $apiRequest->id,
            'collection_id' => $apiRequest->collection_id,
            'name' => $apiRequest->name,
            'method' => $apiRequest->method->value,
            'url' => $apiRequest->url,
            'order' => $apiRequest->order,
            'headers' => $apiRequest->headers,
            'query_params' => $apiRequest->query_params,
            'body' => $apiRequest->body,
            'body_type' => $apiRequest->body_type->value,
            'auth_type' => $apiRequest->auth_type?->value,
            'auth' => self::redactAuth($apiRequest->auth),
            'pre_request_script' => $apiRequest->pre_request_script,
            'test_script' => $apiRequest->test_script,
        ];
    }

    /**
     * The summary shape used inside a workspace tree, where the full body and
     * scripts of every request would dwarf the structure they're hanging off.
     *
     * @return array<string, mixed>
     */
    public static function requestSummary(ApiRequest $apiRequest): array
    {
        return [
            'id' => $apiRequest->id,
            'name' => $apiRequest->name,
            'method' => $apiRequest->method->value,
            'url' => $apiRequest->url,
            'has_test_script' => filled($apiRequest->test_script),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function environment(Environment $environment, bool $withValues = true): array
    {
        return [
            'id' => $environment->id,
            'workspace_id' => $environment->workspace_id,
            'name' => $environment->name,
            'is_active' => $environment->is_active,
            'variables' => $environment->variables
                ->map(fn ($variable) => [
                    'key' => $variable->key,
                    // A secret's value is withheld from tool output even though the
                    // token could read it through the API: it would otherwise be
                    // copied into a transcript that outlives the session, and no
                    // tool needs the plaintext to reference {{the_variable}}.
                    'value' => $variable->is_secret || ! $withValues ? null : $variable->value,
                    'is_secret' => $variable->is_secret,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function historyEntry(RequestHistory $entry, bool $withSnapshot = false): array
    {
        $data = [
            'id' => $entry->id,
            'request_id' => $entry->request_id,
            'method' => $entry->method,
            'url' => $entry->url,
            'status_code' => $entry->status_code,
            'duration_ms' => $entry->duration_ms,
            'executed_at' => $entry->executed_at->toIso8601String(),
        ];

        if ($withSnapshot) {
            $snapshot = $entry->response_snapshot ?? [];
            $body = $snapshot['body'] ?? null;

            $data['response'] = [
                'headers' => $snapshot['headers'] ?? [],
                'body' => is_string($body) ? self::truncate($body) : $body,
                'test_results' => $snapshot['test_results'] ?? [],
                'error' => $snapshot['error'] ?? null,
            ];
        }

        return $data;
    }

    /**
     * Response bodies are unbounded — a single JSON listing can be megabytes —
     * and the whole of one rarely tells the model more than its first pages do.
     */
    public static function truncate(string $body, ?int $limit = null): string
    {
        $limit ??= (int) config('mcp.max_response_characters');

        if (mb_strlen($body) <= $limit) {
            return $body;
        }

        return mb_substr($body, 0, $limit)
            ."\n\n… [truncated: ".mb_strlen($body).' characters total, showing the first '.$limit.']';
    }

    /**
     * Auth blocks hold bearer tokens and passwords. The model needs to know
     * *that* a request authenticates and how, never the credential itself —
     * and unlike a variable it can't even reference the value usefully.
     *
     * @param  array<string, mixed>|null  $auth
     * @return array<string, mixed>|null
     */
    private static function redactAuth(?array $auth): ?array
    {
        if ($auth === null) {
            return null;
        }

        $secretKeys = ['token', 'password', 'value'];

        foreach ($auth as $key => $value) {
            if (in_array($key, $secretKeys, true) && is_string($value) && $value !== '') {
                // A {{variable}} reference isn't a credential, it's a pointer to
                // one — and hiding it would stop the model from reusing the same
                // variable on the next request it builds.
                $auth[$key] = str_contains($value, '{{') ? $value : '[redacted]';
            }
        }

        return $auth;
    }
}
