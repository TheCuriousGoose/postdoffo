<?php

namespace App\Services;

use App\DTOs\ExecutedResponseData;
use App\DTOs\OutgoingRequestData;
use App\DTOs\PreparedRequestData;
use App\Enums\BodyType;
use App\Models\Environment;
use App\Models\Request;
use App\Models\RequestFile;
use App\Services\Scripting\ScriptContext;
use App\Services\Scripting\ScriptRunner;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Fires a single Request through Laravel's HTTP client, running pre-request/test
 * scripts around the call. This is a backend-proxied executor by design: it sidesteps
 * CORS entirely and gives every call a single place to log, rate-limit, and attach
 * workspace secrets without shipping them to the browser.
 *
 * `prepare()` and `finalize()` split that pipeline in two so a caller can hand the
 * resolved request off to the browser to fire instead (e.g. for .test/.local hosts
 * that only resolve on the developer's own machine) and still get pre-request/test
 * script support and history recording on the way back through `finalize()`.
 */
class RequestExecutorService
{
    public function __construct(
        private readonly VariableResolver $variableResolver,
        private readonly ScriptRunner $scriptRunner,
    ) {}

    public function execute(Request $request, ?Environment $environment): ExecutedResponseData
    {
        return $this->sendAndFinalize($request, $this->prepare($request, $environment));
    }

    /**
     * Fires an already-prepared request server-side. Split out from execute() so a
     * request can be prepared once and either fired here or handed to the browser —
     * without re-running the pre-request script a second time.
     */
    public function sendAndFinalize(Request $request, PreparedRequestData $prepared): ExecutedResponseData
    {
        $start = microtime(true);
        $status = null;
        $headers = [];
        $body = null;
        $error = null;

        try {
            $response = $this->send($prepared->outgoing, $request);
            $status = $response->status();
            $headers = $response->headers();
            $body = $response->body();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        return $this->finalize($request, $prepared->variables, $status, $headers, $body, $durationMs, $error);
    }

    /**
     * @param  array<string, string>  $runtimeOverrides  Highest-precedence variable layer — e.g. a Collection Runner's data-file row or a value chained from an earlier request in the same run.
     */
    public function prepare(Request $request, ?Environment $environment, array $runtimeOverrides = []): PreparedRequestData
    {
        $request->loadMissing('collection.workspace');

        $variables = $this->variableResolver->resolve(
            $request->collection,
            $environment,
            $runtimeOverrides,
            $request->collection?->workspace,
        );

        $preContext = new ScriptContext($variables);
        $this->scriptRunner->run($request->pre_request_script, $preContext);
        $variables = $preContext->variables;

        $outgoing = $this->buildOutgoingRequest($request, $variables, $preContext->headerOverrides);

        return new PreparedRequestData($outgoing, $variables);
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, array<int, string>|string>  $headers
     */
    public function finalize(
        Request $request,
        array $variables,
        ?int $status,
        array $headers,
        ?string $body,
        int $durationMs,
        ?string $error,
    ): ExecutedResponseData {
        $testContext = $this->buildTestContext($variables, $status, $body, $headers, $durationMs);
        $this->scriptRunner->run($request->test_script, $testContext);

        return new ExecutedResponseData(
            status: $status,
            headers: $headers,
            body: $body,
            durationMs: $durationMs,
            testResults: $testContext->testResults,
            error: $error,
            variables: $testContext->variables,
        );
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, string>  $headerOverrides
     */
    private function buildOutgoingRequest(Request $request, array $variables, array $headerOverrides): OutgoingRequestData
    {
        $auth = $this->variableResolver->resolveAuth($request, $variables);

        $headers = $this->variableResolver->resolveHeaders($request->collection);

        if ($auth && $auth['location'] === 'header') {
            $headers[$auth['key']] = $auth['value'];
        }

        $headers = [...$headers, ...$this->keyValueListToMap($request->headers), ...$headerOverrides];

        $query = $this->keyValueListToMap($request->query_params);

        if ($auth && $auth['location'] === 'query') {
            $query = [$auth['key'] => $auth['value'], ...$query];
        }

        $interpolated = $this->variableResolver->interpolateArray([
            'url' => $request->url,
            'headers' => $headers,
            'query' => $query,
            'body' => $request->body,
        ], $variables);

        return new OutgoingRequestData(
            method: $request->method,
            url: $interpolated['url'],
            headers: $interpolated['headers'],
            queryParams: $interpolated['query'],
            body: $interpolated['body'],
            bodyType: $request->body_type,
        );
    }

    private function send(OutgoingRequestData $data, Request $request): HttpResponse
    {
        return Http::withHeaders($this->sendableHeaders($data))
            ->timeout(30)
            ->send($data->method->value, $data->url, [
                'query' => $data->queryParams,
                ...$this->bodyOptions($data->bodyType, $data->body, $request),
            ]);
    }

    /**
     * Guzzle only fills in a Content-Type for form bodies when one isn't already
     * set, so an explicit `multipart/form-data` header — which imported Postman
     * collections often carry — would ship without the boundary Guzzle just
     * generated, leaving the target unable to parse the body. Drop it and let
     * Guzzle write the real one, the same way the browser path does.
     *
     * @return array<string, string>
     */
    private function sendableHeaders(OutgoingRequestData $data): array
    {
        if (! in_array($data->bodyType, [BodyType::FormData, BodyType::UrlEncoded], strict: true)) {
            return $data->headers;
        }

        return array_filter(
            $data->headers,
            fn (string $name) => strtolower($name) !== 'content-type',
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Translate the stored body shape into Guzzle request options for the given type.
     *
     * @return array<string, mixed>
     */
    private function bodyOptions(BodyType $type, mixed $body, Request $request): array
    {
        $body = is_array($body) ? $body : [];

        return match ($type) {
            BodyType::None => [],
            BodyType::Raw => ['body' => (string) ($body['raw'] ?? '')],
            BodyType::Json => ['json' => $body['json'] ?? []],
            BodyType::FormData => ['multipart' => $this->fieldsToMultipart($body['fields'] ?? [], $request)],
            BodyType::UrlEncoded => ['form_params' => $this->keyValueListToMap($body['fields'] ?? [])],
        };
    }

    /**
     * Build the multipart parts for a form-data body. A `{"type": "file"}` field
     * carries a request_files id rather than a value, and is streamed off disk
     * here so a large upload never has to sit in memory. A field pointing at a
     * file that no longer exists is dropped, the same as a blank or disabled row.
     *
     * @param  array<int, mixed>  $fields
     * @return array<int, array{name: string, contents: mixed, filename?: string, headers?: array<string, string>}>
     */
    private function fieldsToMultipart(array $fields, Request $request): array
    {
        $files = $this->uploadedFiles($fields, $request);
        $parts = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ($field['enabled'] ?? true) === false) {
                continue;
            }

            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            if (($field['type'] ?? 'text') !== 'file') {
                $parts[] = ['name' => $key, 'contents' => (string) ($field['value'] ?? '')];

                continue;
            }

            $file = $files[(int) ($field['file_id'] ?? 0)] ?? null;
            $stream = $file ? Storage::disk(RequestFile::DISK)->readStream($file->path) : null;

            if (! $file || ! $stream) {
                continue;
            }

            $parts[] = [
                'name' => $key,
                'contents' => $stream,
                'filename' => $file->filename,
                'headers' => ['Content-Type' => $file->mime_type ?? 'application/octet-stream'],
            ];
        }

        return $parts;
    }

    /**
     * Load the uploads a form-data body refers to, scoped to the request being
     * executed. The scoping is the security boundary: send() accepts an outgoing
     * body straight from the browser, so without it a crafted file_id could
     * stream another workspace's upload out to any URL.
     *
     * @param  array<int, mixed>  $fields
     * @return array<int, RequestFile>
     */
    private function uploadedFiles(array $fields, Request $request): array
    {
        $ids = [];

        foreach ($fields as $field) {
            if (is_array($field) && ($field['type'] ?? null) === 'file' && isset($field['file_id'])) {
                $ids[] = (int) $field['file_id'];
            }
        }

        if ($ids === []) {
            return [];
        }

        return RequestFile::forRequest($request->id)
            ->whereKey($ids)
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * @param  array<int, mixed>|null  $list
     * @return array<string, string>
     */
    private function keyValueListToMap(?array $list): array
    {
        $map = [];

        foreach ($list ?? [] as $item) {
            if (! is_array($item) || ($item['enabled'] ?? true) === false) {
                continue;
            }

            $key = $item['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $map[$key] = (string) ($item['value'] ?? '');
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, array<int, string>|string>  $headers
     */
    private function buildTestContext(array $variables, ?int $status, ?string $body, array $headers, int $durationMs): ScriptContext
    {
        $context = new ScriptContext($variables);
        $context->responseStatus = $status;
        $context->responseBody = $body;
        $context->responseTimeMs = $durationMs;

        foreach ($headers as $name => $values) {
            $context->responseHeaders[strtolower($name)] = is_array($values) ? implode(', ', $values) : (string) $values;
        }

        return $context;
    }
}
