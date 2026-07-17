<?php

namespace App\Services\Scripting;

use App\DTOs\TestResultData;

/**
 * Runs a pre-request or test script (one statement per line) against a ScriptContext.
 * A statement that fails to parse/evaluate is recorded as a failed test rather than
 * aborting the whole script, so one bad line doesn't take out the rest of the suite.
 */
class ScriptRunner
{
    public function run(?string $script, ScriptContext $context): void
    {
        foreach ($this->statements($script) as $statement) {
            try {
                (new ScriptExpression($statement))->evaluate($context);
            } catch (ScriptException $e) {
                $context->testResults[] = new TestResultData(
                    name: 'Script error',
                    passed: false,
                    message: "{$e->getMessage()} (in: {$statement})",
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function statements(?string $script): array
    {
        if ($script === null || trim($script) === '') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $script) ?: [];

        $lines = array_map(
            fn (string $line) => rtrim(trim($line), ';'),
            $lines,
        );

        return array_values(array_filter(
            $lines,
            fn (string $line) => $line !== '' && ! str_starts_with($line, '//'),
        ));
    }
}
