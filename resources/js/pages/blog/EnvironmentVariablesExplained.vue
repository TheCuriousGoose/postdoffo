<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BlogPostLayout from '@/layouts/blog/BlogPostLayout.vue';
</script>

<template>
    <Head title="Environment variables in API requests, explained" />

    <BlogPostLayout
        title="Environment variables in API requests, explained"
        description="What environment variables actually solve, how {{variable}} interpolation works, and why secrets need their own handling."
        date="July 22, 2026"
    >
        <p>
            The same request usually needs to hit more than one place: your
            laptop, a staging server, production. Without environment
            variables, that means either editing the URL by hand every time
            or keeping three near-identical copies of every request around.
            Neither scales past a handful of endpoints.
        </p>

        <h2>The mechanism is simple</h2>
        <p>
            An environment is just a named set of key/value pairs, scoped to
            a workspace. Anywhere in a request — the URL, a header, the body
            — you can drop a placeholder like <code v-pre>{{base_url}}</code
            >, and PostDoffo resolves it against whichever environment is
            currently active before the request goes out.
        </p>
        <pre v-pre>{{base_url}}/v1/customers
Authorization: Bearer {{token}}</pre>
        <p>
            Switch the active environment from <em>Staging</em> to
            <em>Production</em> and every request using
            <code v-pre>{{base_url}}</code> now points somewhere else,
            without touching a single request.
        </p>

        <h2>Reading and writing from scripts</h2>
        <p>
            Variables aren't only set by hand. A pre-request or test script
            can read and write them through <code>pm.environment</code> (an
            alias for <code>pm.variables</code>):
        </p>
        <pre>pm.environment.set("timestamp", "123")
pm.environment.get("base_url")</pre>
        <p>
            This is what makes a captured auth token useful across an entire
            collection: a login request's test script sets
            <code>token</code>, and every request after it that references
            <code v-pre>{{token}}</code> picks up whatever was last written.
        </p>

        <h2>Secrets are a separate concern</h2>
        <p>
            Not every variable belongs on screen. An API key or bearer token
            is still just a string value, but PostDoffo lets you mark a
            variable as secret, which masks it in the UI everywhere it would
            otherwise be shown in plain text — the variable still resolves
            correctly when a request runs, it just isn't sitting visible in
            a list for anyone glancing at your screen.
        </p>

        <h2>Where this saves the most time</h2>
        <ul>
            <li>
                Switching between dev, staging and production without
                editing a single request.
            </li>
            <li>
                Sharing a collection with a teammate whose
                <code v-pre>{{base_url}}</code> or credentials are different
                from yours — they set their own environment, the requests
                don't change.
            </li>
            <li>
                Chaining requests where one response feeds the next, via
                <code>pm.environment.set(...)</code> in a test script.
            </li>
        </ul>
        <p>
            See the <Link :href="'/docs/scripting'">scripting reference</Link>
            for the full set of <code>pm.*</code> calls available to a
            script.
        </p>
    </BlogPostLayout>
</template>
