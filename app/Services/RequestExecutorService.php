<?php

namespace App\Services;

use App\DTOs\ExecutedResponseData;
use App\DTOs\OutgoingRequestData;
use App\Enums\BodyType;
use App\Models\Environment;
use App\Models\Request;
use App\Services\Scripting\ScriptContext;
use App\Services\Scripting\ScriptRunner;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fires a single Request through Laravel's HTTP client, running pre-request/test
 * scripts around the call. This is a backend-proxied executor by design: it sidesteps
 * CORS entirely and gives every call a single place to log, rate-limit, and attach
 * workspace secrets without shipping them to the browser.
 */
class RequestExecutorService
{
    public function __construct(
        private readonly VariableResolver $variableResolver,
        private readonly ScriptRunner $scriptRunner,
    ) {}

    public function execute(Request $request, ?Environment $environment): ExecutedResponseData
    {
        $request->loadMissing('collection');

        $variables = $this->variableResolver->resolve($request->collection, $environment);

        $preContext = new ScriptContext($variables);
        $this->scriptRunner->run($request->pre_request_script, $preContext);
        $variables = $preContext->variables;

        $outgoing = $this->buildOutgoingRequest($request, $variables, $preContext->headerOverrides);

        $start = microtime(true);
        $response = null;
        $error = null;

        try {
            $response = $this->send($outgoing);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $testContext = $this->buildTestContext($variables, $response, $durationMs);
        $this->scriptRunner->run($request->test_script, $testContext);

        return new ExecutedResponseData(
            status: $response?->status(),
            headers: $response?->headers() ?? [],
            body: $response?->body(),
            durationMs: $durationMs,
            testResults: $testContext->testResults,
            error: $error,
        );
    }

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, string>  $headerOverrides
     */
    private function buildOutgoingRequest(Request $request, array $variables, array $headerOverrides): OutgoingRequestData
    {
        $headers = [...$this->keyValueListToMap($request->headers), ...$headerOverrides];
        $query = $this->keyValueListToMap($request->query_params);

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

    private function send(OutgoingRequestData $data): HttpResponse
    {
        return Http::withHeaders($data->headers)
            ->timeout(30)
            ->send($data->method->value, $data->url, [
                'query' => $data->queryParams,
                ...$this->bodyOptions($data->bodyType, $data->body),
            ]);
    }

    /**
     * Translate the stored body shape into Guzzle request options for the given type.
     *
     * @return array<string, mixed>
     */
    private function bodyOptions(BodyType $type, mixed $body): array
    {
        $body = is_array($body) ? $body : [];

        return match ($type) {
            BodyType::None => [],
            BodyType::Raw => ['body' => (string) ($body['raw'] ?? '')],
            BodyType::Json => ['json' => $body['json'] ?? []],
            BodyType::FormData => ['multipart' => $this->fieldsToMultipart($body['fields'] ?? [])],
            BodyType::UrlEncoded => ['form_params' => $this->keyValueListToMap($body['fields'] ?? [])],
        };
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, array{name: string, contents: string}>
     */
    private function fieldsToMultipart(array $fields): array
    {
        $parts = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ($field['enabled'] ?? true) === false) {
                continue;
            }

            $key = $field['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $parts[] = ['name' => $key, 'contents' => (string) ($field['value'] ?? '')];
        }

        return $parts;
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
     */
    private function buildTestContext(array $variables, ?HttpResponse $response, int $durationMs): ScriptContext
    {
        $context = new ScriptContext($variables);
        $context->responseStatus = $response?->status();
        $context->responseBody = $response?->body();
        $context->responseTimeMs = $durationMs;

        foreach ($response?->headers() ?? [] as $name => $values) {
            $context->responseHeaders[strtolower($name)] = implode(', ', $values);
        }

        return $context;
    }
}
