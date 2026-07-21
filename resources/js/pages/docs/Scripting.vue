<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';

const sections = [
    { id: 'overview', label: 'Overview' },
    { id: 'syntax', label: 'Syntax' },
    { id: 'response-data', label: 'Reading the response' },
    { id: 'variables', label: 'Variables' },
    { id: 'headers', label: 'Request headers' },
    { id: 'tests', label: 'Assertions' },
    { id: 'values', label: 'Values & operators' },
    { id: 'comments', label: 'Comments' },
    { id: 'limitations', label: "What's not supported" },
    { id: 'examples', label: 'Examples' },
];
</script>

<template>
    <Head title="Scripting reference" />

    <DocsLayout
        title="Scripting reference"
        description="Everything the pre-request and test script boxes can actually do."
        :sections="sections"
    >
        <section id="overview">
            <h2>Overview</h2>
            <p>
                Every request has two script boxes: a
                <strong>pre-request script</strong>, which runs before the
                request goes out, and a <strong>test script</strong>, which
                runs after the response comes back. Both use the same small
                language built around a single <code>pm</code> object.
            </p>
            <p>
                This is <strong>not JavaScript</strong>. It's a deliberately
                tiny, closed grammar &mdash; there's no interpreter and no
                <code>eval()</code> anywhere behind it, so the only things a
                script can do are the exact <code>pm.*</code> calls listed on
                this page. That's a safety property, not a limitation we plan
                to lift, so don't expect loops, <code>if</code>/<code
                    >else</code
                >, or arithmetic to start working later.
            </p>
        </section>

        <section id="syntax">
            <h2>Syntax</h2>
            <p>
                A script is <strong>one statement per line</strong>. Each line
                is evaluated independently &mdash; there's no way for one line
                to affect the parsing of another, and a mistake on one line
                only fails that line, not the rest of the script.
            </p>
            <ul>
                <li>Blank lines are ignored.</li>
                <li>
                    A trailing <code>;</code> at the end of a line is stripped
                    and optional &mdash; you can include one or leave it off.
                </li>
                <li>
                    A line is a statement, either a bare expression (most
                    useful when it's a <code>pm.test(...)</code> or a
                    <code>pm.*.set(...)</code> call — the return value of
                    other expressions is simply discarded).
                </li>
            </ul>
        </section>

        <section id="response-data">
            <h2>Reading the response</h2>
            <p>
                These are only meaningful in a <strong>test script</strong>
                &mdash; in a pre-request script the response hasn't happened
                yet, so they'll all read as <code>null</code>.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Expression</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>pm.response.status</code></td>
                        <td>HTTP status code, as a number</td>
                    </tr>
                    <tr>
                        <td><code>pm.response.responseTime</code></td>
                        <td>Round-trip time in milliseconds</td>
                    </tr>
                    <tr>
                        <td><code>pm.response.body</code></td>
                        <td>The raw response body as a string</td>
                    </tr>
                    <tr>
                        <td><code>pm.response.header("Name")</code></td>
                        <td>
                            A response header value, or <code>null</code> if
                            missing. The name is matched
                            case-insensitively.
                        </td>
                    </tr>
                    <tr>
                        <td><code>pm.response.json</code></td>
                        <td>The whole body, decoded as JSON</td>
                    </tr>
                    <tr>
                        <td><code>pm.response.json.user.id</code></td>
                        <td>
                            Any dotted path into the decoded body. A missing
                            segment anywhere in the path resolves to
                            <code>null</code> rather than raising an error.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section id="variables">
            <h2>Variables</h2>
            <p>
                <code>pm.variables</code> and <code>pm.environment</code> are
                aliases for the same thing &mdash; the current environment's
                variables.
            </p>
            <ul>
                <li>
                    <code>pm.variables.get("key")</code> /
                    <code>pm.environment.get("key")</code> &mdash; reads a
                    variable, or <code>null</code> if it isn't set.
                </li>
                <li>
                    <code>pm.variables.set("key", value)</code> /
                    <code>pm.environment.set("key", value)</code> &mdash;
                    writes a variable. The value is always stored as a
                    string.
                </li>
            </ul>
            <p>
                Variables set from a script are saved back to the active
                environment, so a pre-request script that generates a
                timestamp, or a test script that captures a token from the
                response, is visible to every later request that uses
                <code v-pre>{{variable}}</code> placeholders.
            </p>
        </section>

        <section id="headers">
            <h2>Request headers</h2>
            <p>
                <code>pm.request.headers.set("Name", value)</code> adds or
                overrides a header on the outgoing request. It only has an
                effect from a <strong>pre-request script</strong> &mdash;
                calling it from a test script has no request left to affect.
            </p>
        </section>

        <section id="tests">
            <h2>Assertions</h2>
            <p>
                <code>pm.test("name", condition)</code> records one pass/fail
                result, shown against the request after it runs.
                <code>condition</code> is any expression from this page &mdash;
                typically a comparison.
            </p>
            <pre>pm.test("status is 200", pm.response.status == 200)</pre>
        </section>

        <section id="values">
            <h2>Values &amp; operators</h2>
            <p><strong>Literals:</strong></p>
            <ul>
                <li>
                    Strings: <code>"double"</code> or <code>'single'</code>
                    quoted, with <code>\"</code>, <code>\'</code>,
                    <code>\\</code> escapes.
                </li>
                <li>Numbers: <code>42</code>, <code>3.14</code>, <code>-1</code>.</li>
                <li>
                    <code>true</code>, <code>false</code>, <code>null</code>.
                </li>
            </ul>
            <p><strong>Operators:</strong></p>
            <ul>
                <li>
                    Comparison: <code>==</code> <code>!=</code> <code>&gt;</code>
                    <code>&gt;=</code> <code>&lt;</code> <code>&lt;=</code>.
                    If both sides look numeric (including numeric strings),
                    they're compared as numbers.
                </li>
                <li>Logical: <code>&amp;&amp;</code> <code>||</code> <code>!</code>.</li>
                <li>Parentheses for grouping: <code>(a == b) &amp;&amp; c</code>.</li>
            </ul>
            <p>
                <strong>Truthiness</strong> follows one rule: an empty string
                is falsy, everything else is truthy. That means the string
                <code>"0"</code> is <em>truthy</em> here, which is the
                opposite of JavaScript &mdash; a common surprise when a
                <code>pm.response.json.someField</code> path happens to hold
                the string <code>"0"</code>.
            </p>
        </section>

        <section id="comments">
            <h2>Comments</h2>
            <p>
                A line is a comment only when it starts with <code>//</code>
                once leading whitespace is trimmed. The whole line is
                ignored.
            </p>
            <pre>// capture the auth token for later requests
pm.environment.set("token", pm.response.json.access_token)</pre>
            <p>
                There's no support for a trailing comment after code on the
                same line &mdash; keep comments on their own line.
            </p>
        </section>

        <section id="limitations">
            <h2>What's not supported</h2>
            <p>
                Because the grammar is intentionally closed, none of the
                following exist, even though they're common in Postman or
                plain JavaScript:
            </p>
            <ul>
                <li>
                    Control flow &mdash; no <code>if</code>/<code>else</code>,
                    loops, or ternaries.
                </li>
                <li>
                    Arithmetic &mdash; no <code>+</code>, <code>-</code>,
                    <code>*</code>, <code>/</code>.
                </li>
                <li>Assignment operators, multi-line blocks, or functions.</li>
                <li>
                    <code>pm.expect(...)</code>-style assertion chains &mdash;
                    use <code>pm.test(name, condition)</code> instead.
                </li>
                <li>
                    <code>pm.sendRequest()</code>, <code>console.log()</code>,
                    <code>pm.cookies</code>, <code>pm.globals</code>,
                    <code>pm.collectionVariables</code>, or
                    <code>pm.info</code>.
                </li>
            </ul>
        </section>

        <section id="examples">
            <h2>Examples</h2>
            <p><strong>Pre-request:</strong> set a header from a variable.</p>
            <pre v-pre>pm.request.headers.set("X-Trace-Id", "{{traceId}}")</pre>

            <p><strong>Pre-request:</strong> stamp a variable for later use.</p>
            <pre>pm.variables.set("timestamp", "123")</pre>

            <p><strong>Test:</strong> check the status and capture a token.</p>
            <pre>pm.test("status is 200", pm.response.status == 200)
pm.test("has access token", pm.response.json.access_token != null)
pm.environment.set("token", pm.response.json.access_token)</pre>

            <p><strong>Test:</strong> a compound condition.</p>
            <pre>pm.test("fast and successful", pm.response.responseTime < 500 && pm.response.status == 200)</pre>
        </section>
    </DocsLayout>
</template>
