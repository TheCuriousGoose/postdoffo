<?php

namespace App\Services\Scripting;

use App\DTOs\TestResultData;

/**
 * A hand-rolled, closed-grammar evaluator for one statement of a pre-request/test
 * script, e.g.:
 *
 *   pm.test("status is 200", pm.response.status == 200)
 *   pm.environment.set("token", pm.response.json.access_token)
 *   pm.request.headers.set("X-Trace-Id", "{{traceId}}")
 *
 * This is intentionally NOT a JS interpreter and never calls eval()/PHP eval-alikes.
 * The only things a script can do are the whitelisted pm.* functions/properties
 * dispatched in evaluateCall()/evaluateProperty() below, so the "sandbox" is the
 * grammar itself rather than an OS-level sandbox — there is no path from script
 * text to arbitrary code execution.
 */
final class ScriptExpression
{
    /** @var array<int, array{type: string, value: mixed}> */
    private array $tokens;

    private int $pos = 0;

    public function __construct(string $source)
    {
        $this->tokens = self::tokenize($source);
    }

    public function evaluate(ScriptContext $context): mixed
    {
        $this->pos = 0;

        if ($this->tokens === []) {
            return null;
        }

        $value = $this->parseOr($context);

        if (array_key_exists($this->pos, $this->tokens)) {
            throw new ScriptException('Unexpected token after expression.');
        }

        return $value;
    }

    private function parseOr(ScriptContext $context): mixed
    {
        $value = $this->parseAnd($context);

        while ($this->peekIsOp('||')) {
            $this->pos++;
            $right = $this->parseAnd($context);
            $value = self::truthy($value) || self::truthy($right);
        }

        return $value;
    }

    private function parseAnd(ScriptContext $context): mixed
    {
        $value = $this->parseComparison($context);

        while ($this->peekIsOp('&&')) {
            $this->pos++;
            $right = $this->parseComparison($context);
            $value = self::truthy($value) && self::truthy($right);
        }

        return $value;
    }

    private function parseComparison(ScriptContext $context): mixed
    {
        $left = $this->parsePrimary($context);

        $op = $this->peekOpIn(['==', '!=', '>=', '<=', '>', '<']);

        if ($op === null) {
            return $left;
        }

        $this->pos++;
        $right = $this->parsePrimary($context);

        return self::compare($op, $left, $right);
    }

    private function parsePrimary(ScriptContext $context): mixed
    {
        $token = $this->tokens[$this->pos] ?? null;

        if ($token === null) {
            throw new ScriptException('Unexpected end of expression.');
        }

        if ($token['type'] === 'OP' && $token['value'] === '!') {
            $this->pos++;

            return ! self::truthy($this->parsePrimary($context));
        }

        if ($token['type'] === 'PUNCT' && $token['value'] === '(') {
            $this->pos++;
            $value = $this->parseOr($context);
            $this->expectPunct(')');

            return $value;
        }

        if ($token['type'] === 'STRING') {
            $this->pos++;

            return $token['value'];
        }

        if ($token['type'] === 'NUMBER') {
            $this->pos++;

            return $token['value'];
        }

        if ($token['type'] === 'IDENT') {
            return match ($token['value']) {
                'true' => $this->consumeIdent(true),
                'false' => $this->consumeIdent(false),
                'null' => $this->consumeIdent(null),
                'pm' => $this->parsePmPath($context),
                default => throw new ScriptException("Unknown identifier '{$token['value']}'."),
            };
        }

        throw new ScriptException('Unexpected token in expression.');
    }

    private function consumeIdent(mixed $value): mixed
    {
        $this->pos++;

        return $value;
    }

    private function parsePmPath(ScriptContext $context): mixed
    {
        $this->pos++; // consume 'pm'
        $segments = [];

        while ($this->peekIsPunct('.')) {
            $this->pos++;
            $segment = $this->tokens[$this->pos] ?? null;

            if ($segment === null || ! in_array($segment['type'], ['IDENT', 'NUMBER'], true)) {
                throw new ScriptException('Expected property name after "." in pm path.');
            }

            $segments[] = (string) $segment['value'];
            $this->pos++;
        }

        if ($segments === []) {
            throw new ScriptException('Expected a property after "pm.".');
        }

        if ($this->peekIsPunct('(')) {
            $this->pos++;
            $args = $this->parseArgs($context);
            $this->expectPunct(')');

            return $this->evaluateCall(implode('.', $segments), $args, $context);
        }

        return $this->evaluateProperty($segments, $context);
    }

    /**
     * @return array<int, mixed>
     */
    private function parseArgs(ScriptContext $context): array
    {
        $args = [];

        if ($this->peekIsPunct(')')) {
            return $args;
        }

        $args[] = $this->parseOr($context);

        while ($this->peekIsPunct(',')) {
            $this->pos++;
            $args[] = $this->parseOr($context);
        }

        return $args;
    }

    /**
     * @param  array<int, string>  $segments
     */
    private function evaluateProperty(array $segments, ScriptContext $context): mixed
    {
        $path = implode('.', $segments);

        return match (true) {
            $path === 'response.status' => $context->responseStatus,
            $path === 'response.responseTime' => $context->responseTimeMs,
            $path === 'response.body' => $context->responseBody,
            str_starts_with($path, 'response.json') => $context->jsonPath(
                ltrim(substr($path, strlen('response.json')), '.')
            ),
            default => throw new ScriptException("Unsupported property 'pm.{$path}'."),
        };
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function evaluateCall(string $fnPath, array $args, ScriptContext $context): mixed
    {
        return match ($fnPath) {
            'response.header' => $context->responseHeaders[strtolower((string) ($args[0] ?? ''))] ?? null,
            'variables.get', 'environment.get' => $context->variables[(string) ($args[0] ?? '')] ?? null,
            'variables.set' => $this->setVariable($context, $args),
            'environment.set' => $this->setEnvironmentVariable($context, $args),
            'request.headers.set' => $this->setHeader($context, $args),
            'test' => $this->recordTest($context, $args),
            default => throw new ScriptException("Unsupported function 'pm.{$fnPath}()'."),
        };
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function setVariable(ScriptContext $context, array $args): null
    {
        [$key, $value] = [$args[0] ?? null, $args[1] ?? null];

        if (! is_string($key)) {
            throw new ScriptException('A variable key must be a string.');
        }

        $context->variables[$key] = (string) $value;

        return null;
    }

    /**
     * Unlike pm.variables.set() — script/run-scoped only — this also records the
     * write in $context->environmentUpdates, so RequestExecutorService persists it
     * to the environment's stored variables once the script finishes running.
     *
     * @param  array<int, mixed>  $args
     */
    private function setEnvironmentVariable(ScriptContext $context, array $args): null
    {
        $this->setVariable($context, $args);

        $context->environmentUpdates[(string) $args[0]] = (string) ($args[1] ?? null);

        return null;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function setHeader(ScriptContext $context, array $args): null
    {
        [$key, $value] = [$args[0] ?? null, $args[1] ?? null];

        if (! is_string($key)) {
            throw new ScriptException('pm.request.headers.set() requires a string key.');
        }

        $context->headerOverrides[$key] = (string) $value;

        return null;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function recordTest(ScriptContext $context, array $args): null
    {
        [$name, $passed] = [$args[0] ?? 'unnamed test', $args[1] ?? null];

        $context->testResults[] = new TestResultData((string) $name, self::truthy($passed));

        return null;
    }

    private static function truthy(mixed $value): bool
    {
        if (is_string($value)) {
            return $value !== '';
        }

        return (bool) $value;
    }

    private static function compare(string $op, mixed $left, mixed $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            [$left, $right] = [(float) $left, (float) $right];
        }

        return match ($op) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => throw new ScriptException("Unsupported operator '{$op}'."),
        };
    }

    private function peekIsOp(string $value): bool
    {
        $token = $this->tokens[$this->pos] ?? null;

        return $token !== null && $token['type'] === 'OP' && $token['value'] === $value;
    }

    /**
     * @param  array<int, string>  $values
     */
    private function peekOpIn(array $values): ?string
    {
        $token = $this->tokens[$this->pos] ?? null;

        if ($token !== null && $token['type'] === 'OP' && in_array($token['value'], $values, true)) {
            return $token['value'];
        }

        return null;
    }

    private function peekIsPunct(string $value): bool
    {
        $token = $this->tokens[$this->pos] ?? null;

        return $token !== null && $token['type'] === 'PUNCT' && $token['value'] === $value;
    }

    private function expectPunct(string $value): void
    {
        if (! $this->peekIsPunct($value)) {
            throw new ScriptException("Expected '{$value}'.");
        }

        $this->pos++;
    }

    /**
     * @return array<int, array{type: string, value: mixed}>
     */
    private static function tokenize(string $source): array
    {
        $pattern = '/\s*(?:'
            .'("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')'   // 1: string
            .'|(-?\d+(?:\.\d+)?)'                                 // 2: number
            .'|(==|!=|>=|<=|&&|\|\||[><!])'                       // 3: operator
            .'|([A-Za-z_][A-Za-z0-9_]*)'                          // 4: ident
            .'|([.(),])'                                          // 5: punct
            .')/';

        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);

        $tokens = [];

        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $tokens[] = ['type' => 'STRING', 'value' => self::unquote($match[1])];
            } elseif (($match[2] ?? '') !== '') {
                $tokens[] = ['type' => 'NUMBER', 'value' => str_contains($match[2], '.') ? (float) $match[2] : (int) $match[2]];
            } elseif (($match[3] ?? '') !== '') {
                $tokens[] = ['type' => 'OP', 'value' => $match[3]];
            } elseif (($match[4] ?? '') !== '') {
                $tokens[] = ['type' => 'IDENT', 'value' => $match[4]];
            } elseif (($match[5] ?? '') !== '') {
                $tokens[] = ['type' => 'PUNCT', 'value' => $match[5]];
            }
        }

        return $tokens;
    }

    private static function unquote(string $raw): string
    {
        $inner = substr($raw, 1, -1);

        return str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $inner);
    }
}
