<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('scripting-reference')]
#[Title('Test script reference')]
#[Uri('postdoffo://scripting-reference')]
#[MimeType('text/markdown')]
#[Description(
    'The complete grammar for pre-request and test scripts. Read this before writing a '
    .'test_script or pre_request_script: the language looks like the Postman one but is a small '
    .'closed set of statements, not JavaScript, and anything outside it fails at run time.'
)]
class ScriptingReference extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'MARKDOWN'
            # PostDoffo script reference

            Scripts are **not JavaScript**. Each line is one statement, evaluated against a
            fixed grammar. There are no variables, loops, functions, `if`, `const`, callbacks
            or object literals — a statement outside the grammar below does not throw, it is
            recorded as a failed test named "Script error", so a script that looks right but
            uses unsupported syntax shows up as a failing suite rather than a crash.

            Blank lines are ignored, and a line starting with `//` is a comment. A trailing
            semicolon is allowed but not needed.

            ## Assertions

            ```
            pm.test("status is 200", pm.response.status == 200)
            pm.test("came back quickly", pm.response.responseTime < 500)
            pm.test("has a token", pm.response.json.access_token != null)
            ```

            `pm.test(name, condition)` takes exactly two arguments: a name and an expression
            that is evaluated to true or false. There is no callback form —
            `pm.test("x", function () { ... })` is not supported.

            ## Reading the response

            | Expression | Value |
            | --- | --- |
            | `pm.response.status` | HTTP status code, e.g. `200` |
            | `pm.response.responseTime` | Round trip in milliseconds |
            | `pm.response.body` | The raw body as a string |
            | `pm.response.json` | The whole decoded JSON body |
            | `pm.response.json.data.id` | Dotted path into the decoded body |
            | `pm.response.header("content-type")` | One response header, case-insensitive |

            A path that does not exist evaluates to `null` rather than erroring, so
            `pm.test("has id", pm.response.json.id != null)` is the way to assert presence.
            Array elements are reached by their index as another path segment:
            `pm.response.json.items.0.name`.

            ## Variables

            ```
            pm.environment.set("token", pm.response.json.access_token)
            pm.variables.set("order_id", pm.response.json.id)
            pm.environment.get("base_url")
            ```

            `pm.environment.*` and `pm.variables.*` are the same store. A value set in one
            request is visible to the requests that follow it in the same `run_collection`
            call — this is how a login request hands a token to everything after it. The
            value lives for that run only; it is not written back to the saved environment,
            so a single `execute_request` cannot pick up a token set by an earlier one.

            ## Pre-request scripts

            The same grammar, run before the request is sent. The response accessors are not
            populated yet. Useful statements:

            ```
            pm.request.headers.set("X-Request-Id", "{{run_id}}")
            pm.variables.set("attempt", 1)
            ```

            A `{{variable}}` inside a string literal is interpolated the same way it is in a
            url, header or body.

            ## Operators

            Comparison: `==`, `!=`, `>`, `>=`, `<`, `<=` — numeric when both sides are
            numeric, otherwise loose comparison. Logic: `&&`, `||`, `!`, and parentheses for
            grouping. Literals: strings in single or double quotes, numbers, `true`, `false`,
            `null`.

            ```
            pm.test("created or accepted", pm.response.status == 201 || pm.response.status == 202)
            pm.test("not an error", !(pm.response.status >= 400))
            ```

            ## A worked example

            A login request storing its token:

            ```
            pm.test("login succeeded", pm.response.status == 200)
            pm.test("returned a token", pm.response.json.access_token != null)
            pm.environment.set("token", pm.response.json.access_token)
            ```

            A request using it, with `Authorization: Bearer {{token}}` as a header:

            ```
            pm.test("status is 200", pm.response.status == 200)
            pm.test("json response", pm.response.header("content-type") != null)
            pm.test("returns the current user", pm.response.json.data.email != null)
            ```
            MARKDOWN);
    }
}
