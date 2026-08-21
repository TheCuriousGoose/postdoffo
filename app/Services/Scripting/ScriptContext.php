<?php

namespace App\Services\Scripting;

use App\DTOs\TestResultData;

/**
 * Mutable state threaded through a pre-request/test script run. Scripts read and
 * write through the whitelisted pm.* surface only (see ScriptExpression) — there is
 * no path from script text to arbitrary PHP execution.
 */
final class ScriptContext
{
    /** @var array<string, string> Header overrides set via pm.request.headers.set() during a pre-request script. */
    public array $headerOverrides = [];

    /**
     * Values written via pm.environment.set() — unlike pm.variables.set(), these are
     * meant to outlive this one run, so RequestExecutorService writes them back to the
     * environment's stored variables once the script finishes.
     *
     * @var array<string, string>
     */
    public array $environmentUpdates = [];

    /** @var array<int, TestResultData> */
    public array $testResults = [];

    public ?int $responseStatus = null;

    /** @var array<string, string> Lowercase header name => value. */
    public array $responseHeaders = [];

    public ?string $responseBody = null;

    public ?int $responseTimeMs = null;

    private mixed $decodedJsonBody = false; // false = not decoded yet, null = decode failed

    /**
     * @param  array<string, string>  $variables
     */
    public function __construct(public array $variables) {}

    public function jsonPath(string $path): mixed
    {
        if ($this->decodedJsonBody === false) {
            $this->decodedJsonBody = $this->responseBody !== null
                ? json_decode($this->responseBody, true)
                : null;
        }

        if ($path === '') {
            return $this->decodedJsonBody;
        }

        $value = $this->decodedJsonBody;

        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            return null;
        }

        return $value;
    }
}
