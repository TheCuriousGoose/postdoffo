<?php

namespace App\DTOs;

final readonly class ExecutedResponseData
{
    /**
     * @param  array<string, array<int, string>>  $headers
     * @param  array<int, TestResultData>  $testResults
     * @param  array<string, string>  $variables
     * @param  array<int, array{id: int, key: string, value: string}>  $environmentUpdates  Environment variable rows written via pm.environment.set() (pre-request and/or test script), already persisted by the time this is built — the frontend applies these to its own copy of the environment rather than re-fetching it.
     */
    public function __construct(
        public ?int $status,
        public array $headers,
        public ?string $body,
        public int $durationMs,
        public array $testResults = [],
        public ?string $error = null,
        public array $variables = [],
        public array $environmentUpdates = [],
    ) {}

    public function ok(): bool
    {
        return $this->status !== null && $this->status < 400;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
            'duration_ms' => $this->durationMs,
            'test_results' => array_map(fn (TestResultData $r) => $r->toArray(), $this->testResults),
            'error' => $this->error,
            'ok' => $this->ok(),
            'variables' => $this->variables,
            'environment_updates' => $this->environmentUpdates,
        ];
    }
}
