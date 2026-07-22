<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BlogPostLayout from '@/layouts/blog/BlogPostLayout.vue';
</script>

<template>
    <Head title="How to test a REST API without writing a test suite" />

    <BlogPostLayout
        title="How to test a REST API without writing a test suite"
        description="Using PostDoffo's pm.test assertions to check status codes, response shape and timing on every request you send."
        date="July 22, 2026"
    >
        <p>
            Most API bugs get caught by hand: send a request, eyeball the
            response, move on. That works until the endpoint changes under
            you and nobody notices for a week. A test script turns that
            manual check into something that runs every time, automatically.
        </p>

        <h2>The two script boxes</h2>
        <p>
            Every request in PostDoffo has a
            <strong>pre-request script</strong>, which runs before the call
            goes out, and a <strong>test script</strong>, which runs after
            the response comes back. Both use the same small language built
            around a <code>pm</code> object — see the full
            <Link :href="'/docs/scripting'">scripting reference</Link> for
            everything it supports.
        </p>

        <h2>Asserting on the response</h2>
        <p>
            A test is one line: a name and a condition.
        </p>
        <pre>pm.test("status is 200", pm.response.status == 200)
pm.test("responded under 500ms", pm.response.responseTime &lt; 500)</pre>
        <p>
            Both run every time you send the request, and the pass/fail shows
            up right next to the response. <code>pm.response.json</code>
            decodes the body, and any dotted path into it — like
            <code>pm.response.json.user.id</code> — resolves to
            <code>null</code> instead of throwing if a field is missing, so
            one absent key doesn't break every other assertion in the
            script.
        </p>
        <pre>pm.test("has a user id", pm.response.json.user.id != null)
pm.test("currency is usd", pm.response.json.currency == "usd")</pre>

        <h2>Capturing values for the next request</h2>
        <p>
            A test script can also write to the active environment, which is
            how a token from a login response ends up available to every
            request after it:
        </p>
        <pre>pm.test("has access token", pm.response.json.access_token != null)
pm.environment.set("token", pm.response.json.access_token)</pre>
        <p>
            From there, any later request can reference
            <code v-pre>{{token}}</code> in a header or URL, and it resolves
            to whatever the login request just set.
        </p>

        <h2>What this catches</h2>
        <p>
            A handful of assertions per endpoint — status code, a couple of
            required fields, a rough timing bound — catches the class of bug
            that's easy to miss by eye: a field silently renamed, a status
            code that quietly changed from 200 to 204, a response that got
            slower without anyone noticing. None of it requires a separate
            test runner or build step; it runs the moment you hit send.
        </p>
    </BlogPostLayout>
</template>
