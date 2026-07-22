<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BlogPostLayout from '@/layouts/blog/BlogPostLayout.vue';
</script>

<template>
    <Head title="What actually happens when you import a Postman collection" />

    <BlogPostLayout
        title="What actually happens when you import a Postman collection"
        description="A walk through the Postman v2.1 collection format: what folders, auth and headers look like on the wire, and where they land in PostDoffo."
        date="July 22, 2026"
    >
        <p>
            A Postman collection export is just JSON. Once you've seen the
            shape of it, "import" stops being a black box — it's a
            straightforward walk through a tree, mapping one structure onto
            another.
        </p>

        <h2>The shape of a v2.1 export</h2>
        <p>
            Every collection has an <code>info</code> block (name,
            description, the schema version) and an <code>item</code> array.
            That array is where it gets interesting: each entry is either a
            request or a folder, and folders are just items with their own
            nested <code>item</code> array. A collection with folders inside
            folders is really just this structure nested a few levels deep.
        </p>
        <p>
            A request item carries a <code>request</code> object: the method,
            a <code>url</code> (either a plain string or a structured object
            with <code>raw</code>, <code>host</code> and <code>path</code>),
            a <code>header</code> array of name/value pairs, and a
            <code>body</code> object whose <code>mode</code> says whether
            it's raw text, form data, or URL-encoded fields.
        </p>

        <h2>Where each part lands</h2>
        <table>
            <thead>
                <tr>
                    <th>In the export</th>
                    <th>In PostDoffo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nested <code>item</code> arrays</td>
                    <td>Nested folders inside the collection tree</td>
                </tr>
                <tr>
                    <td><code>request.header</code></td>
                    <td>Request headers, editable per request</td>
                </tr>
                <tr>
                    <td><code>request.body</code></td>
                    <td>The request body, in whichever mode it was saved</td>
                </tr>
                <tr>
                    <td><code>request.auth</code></td>
                    <td>
                        Bearer, Basic or API key auth, set on the request or
                        inherited from the parent folder
                    </td>
                </tr>
            </tbody>
        </table>

        <h2>The part that doesn't carry over</h2>
        <p>
            Postman requests can carry an <code>event</code> array — entries
            with <code>listen: "prerequest"</code> or
            <code>listen: "test"</code>, each wrapping a
            <code>script.exec</code> array of plain JavaScript lines. This is
            the one part of the format that doesn't translate directly.
        </p>
        <p>
            PostDoffo's pre-request and test scripts don't run JavaScript.
            They run a small, closed grammar built around a single
            <code>pm</code> object — no <code>eval()</code>, no arbitrary
            code execution, just the exact set of <code>pm.*</code> calls
            documented on the
            <Link :href="'/docs/scripting'">scripting reference</Link>. An
            imported <code>event.script.exec</code> array shows up as plain
            text, not a working script, and needs to be rewritten by hand
            against that grammar. For most requests that's a handful of
            lines: a <code>pm.test(...)</code> per assertion, a
            <code>pm.environment.set(...)</code> to capture a token. It's a
            deliberate trade-off — a script that can't do arbitrary things
            can't do arbitrary damage either.
        </p>

        <h2>Try it</h2>
        <p>
            Export a collection from Postman as
            <strong>Collection v2.1</strong>, then use Import inside a
            PostDoffo workspace. See the
            <Link :href="'/import/postman'">import guide</Link> for the full
            walkthrough.
        </p>
    </BlogPostLayout>
</template>
