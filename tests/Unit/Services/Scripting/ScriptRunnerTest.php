<?php

namespace Tests\Unit\Services\Scripting;

use App\Services\Scripting\ScriptContext;
use App\Services\Scripting\ScriptRunner;
use PHPUnit\Framework\TestCase;

class ScriptRunnerTest extends TestCase
{
    public function test_pre_request_script_can_set_variables_and_headers(): void
    {
        $context = new ScriptContext(['existing' => 'value']);

        (new ScriptRunner)->run(<<<'SCRIPT'
            pm.variables.set("token", "abc123")
            pm.request.headers.set("X-Trace-Id", "trace-42")
            SCRIPT, $context);

        $this->assertSame('abc123', $context->variables['token']);
        $this->assertSame('trace-42', $context->headerOverrides['X-Trace-Id']);
        $this->assertSame('value', $context->variables['existing']);
    }

    public function test_test_script_records_passing_and_failing_assertions(): void
    {
        $context = new ScriptContext([]);
        $context->responseStatus = 200;
        $context->responseBody = json_encode(['id' => 42, 'success' => true]);

        (new ScriptRunner)->run(<<<'SCRIPT'
            pm.test("status is 200", pm.response.status == 200)
            pm.test("status is 404", pm.response.status == 404)
            pm.test("has id", pm.response.json.id == 42)
            pm.test("success flag and status ok", pm.response.json.success == true && pm.response.status == 200)
            SCRIPT, $context);

        $this->assertCount(4, $context->testResults);
        $this->assertTrue($context->testResults[0]->passed);
        $this->assertFalse($context->testResults[1]->passed);
        $this->assertTrue($context->testResults[2]->passed);
        $this->assertTrue($context->testResults[3]->passed);
    }

    public function test_unresolvable_json_path_is_null_not_an_error(): void
    {
        $context = new ScriptContext([]);
        $context->responseBody = json_encode(['id' => 1]);

        (new ScriptRunner)->run('pm.test("missing field", pm.response.json.nope == null)', $context);

        $this->assertTrue($context->testResults[0]->passed);
    }

    public function test_comments_and_blank_lines_are_ignored(): void
    {
        $context = new ScriptContext([]);
        $context->responseStatus = 200;

        (new ScriptRunner)->run(<<<'SCRIPT'
            // this is a comment

            pm.test("ok", pm.response.status == 200)
            SCRIPT, $context);

        $this->assertCount(1, $context->testResults);
    }

    public function test_an_invalid_statement_is_reported_as_a_failed_test_without_aborting(): void
    {
        $context = new ScriptContext([]);
        $context->responseStatus = 200;

        (new ScriptRunner)->run(<<<'SCRIPT'
            pm.doesNotExist("boom")
            pm.test("still runs", pm.response.status == 200)
            SCRIPT, $context);

        $this->assertCount(2, $context->testResults);
        $this->assertFalse($context->testResults[0]->passed);
        $this->assertSame('Script error', $context->testResults[0]->name);
        $this->assertTrue($context->testResults[1]->passed);
    }

    public function test_variables_get_reads_current_variable_map(): void
    {
        $context = new ScriptContext(['base_url' => 'https://api.example.com']);

        (new ScriptRunner)->run(
            'pm.test("has base url", pm.variables.get("base_url") == "https://api.example.com")',
            $context,
        );

        $this->assertTrue($context->testResults[0]->passed);
    }

    public function test_response_header_lookup_is_case_insensitive(): void
    {
        $context = new ScriptContext([]);
        $context->responseHeaders = ['content-type' => 'application/json'];

        (new ScriptRunner)->run(
            'pm.test("has content type", pm.response.header("Content-Type") == "application/json")',
            $context,
        );

        $this->assertTrue($context->testResults[0]->passed);
    }
}
