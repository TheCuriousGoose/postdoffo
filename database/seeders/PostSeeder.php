<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        $publishedAt = Carbon::parse('2026-07-22 09:00:00');

        $posts = [
            [
                'slug' => 'import-postman-collections',
                'title' => 'What actually happens when you import a Postman collection',
                'excerpt' => 'A walk through the Postman v2.1 collection format: what folders, auth and headers look like on the wire, and where they land in PostDoffo.',
                'body' => <<<'MD'
                    A Postman collection export is just JSON. Once you've seen the shape of it, "import" stops being a black box — it's a straightforward walk through a tree, mapping one structure onto another.

                    ## The shape of a v2.1 export

                    Every collection has an `info` block (name, description, the schema version) and an `item` array. That array is where it gets interesting: each entry is either a request or a folder, and folders are just items with their own nested `item` array. A collection with folders inside folders is really just this structure nested a few levels deep.

                    A request item carries a `request` object: the method, a `url` (either a plain string or a structured object with `raw`, `host` and `path`), a `header` array of name/value pairs, and a `body` object whose `mode` says whether it's raw text, form data, or URL-encoded fields.

                    ## Where each part lands

                    | In the export | In PostDoffo |
                    | --- | --- |
                    | Nested `item` arrays | Nested folders inside the collection tree |
                    | `request.header` | Request headers, editable per request |
                    | `request.body` | The request body, in whichever mode it was saved |
                    | `request.auth` | Bearer, Basic or API key auth, on the request or inherited from the parent folder |

                    ## The part that doesn't carry over

                    Postman requests can carry an `event` array — entries with `listen: "prerequest"` or `listen: "test"`, each wrapping a `script.exec` array of plain JavaScript lines. This is the one part of the format that doesn't translate directly.

                    PostDoffo's pre-request and test scripts don't run JavaScript. They run a small, closed grammar built around a single `pm` object — no `eval()`, no arbitrary code execution, just the exact set of `pm.*` calls documented on the [scripting reference](/docs/scripting). An imported script shows up as plain text, not a working script, and needs to be rewritten by hand against that grammar. For most requests that's a handful of lines: a `pm.test(...)` per assertion, a `pm.environment.set(...)` to capture a token. It's a deliberate trade-off — a script that can't do arbitrary things can't do arbitrary damage either.

                    ## Try it

                    Export a collection from Postman as **Collection v2.1**, then use Import inside a PostDoffo workspace. See the [import guide](/import/postman) for the full walkthrough.
                    MD,
            ],
            [
                'slug' => 'how-to-test-a-rest-api',
                'title' => 'How to test a REST API without writing a test suite',
                'excerpt' => "Using PostDoffo's pm.test assertions to check status codes, response shape and timing on every request you send.",
                'body' => <<<'MD'
                    Most API bugs get caught by hand: send a request, eyeball the response, move on. That works until the endpoint changes under you and nobody notices for a week. A test script turns that manual check into something that runs every time, automatically.

                    ## The two script boxes

                    Every request in PostDoffo has a **pre-request script**, which runs before the call goes out, and a **test script**, which runs after the response comes back. Both use the same small language built around a `pm` object — see the full [scripting reference](/docs/scripting) for everything it supports.

                    ## Asserting on the response

                    A test is one line: a name and a condition.

                    ```
                    pm.test("status is 200", pm.response.status == 200)
                    pm.test("responded under 500ms", pm.response.responseTime < 500)
                    ```

                    Both run every time you send the request, and the pass/fail shows up right next to the response. `pm.response.json` decodes the body, and any dotted path into it — like `pm.response.json.user.id` — resolves to `null` instead of throwing if a field is missing, so one absent key doesn't break every other assertion in the script.

                    ```
                    pm.test("has a user id", pm.response.json.user.id != null)
                    pm.test("currency is usd", pm.response.json.currency == "usd")
                    ```

                    ## Capturing values for the next request

                    A test script can also write to the active environment, which is how a token from a login response ends up available to every request after it:

                    ```
                    pm.test("has access token", pm.response.json.access_token != null)
                    pm.environment.set("token", pm.response.json.access_token)
                    ```

                    From there, any later request can reference the `token` variable in a header or URL, and it resolves to whatever the login request just set.

                    ## What this catches

                    A handful of assertions per endpoint — status code, a couple of required fields, a rough timing bound — catches the class of bug that's easy to miss by eye: a field silently renamed, a status code that quietly changed from 200 to 204, a response that got slower without anyone noticing. None of it requires a separate test runner or build step; it runs the moment you hit send.
                    MD,
            ],
            [
                'slug' => 'environment-variables-explained',
                'title' => 'Environment variables in API requests, explained',
                'excerpt' => 'What environment variables actually solve, how variable interpolation works, and why secrets need their own handling.',
                'body' => <<<'MD'
                    The same request usually needs to hit more than one place: your laptop, a staging server, production. Without environment variables, that means either editing the URL by hand every time or keeping three near-identical copies of every request around. Neither scales past a handful of endpoints.

                    ## The mechanism is simple

                    An environment is just a named set of key/value pairs, scoped to a workspace. Anywhere in a request — the URL, a header, the body — you can drop a placeholder like `{{base_url}}`, and PostDoffo resolves it against whichever environment is currently active before the request goes out.

                    Switch the active environment from *Staging* to *Production* and every request using `{{base_url}}` now points somewhere else, without touching a single request.

                    ## Reading and writing from scripts

                    Variables aren't only set by hand. A pre-request or test script can read and write them through `pm.environment` (an alias for `pm.variables`):

                    ```
                    pm.environment.set("timestamp", "123")
                    pm.environment.get("base_url")
                    ```

                    This is what makes a captured auth token useful across an entire collection: a login request's test script sets `token`, and every request after it that references `{{token}}` picks up whatever was last written.

                    ## Secrets are a separate concern

                    Not every variable belongs on screen. An API key or bearer token is still just a string value, but PostDoffo lets you mark a variable as secret, which masks it in the UI everywhere it would otherwise be shown in plain text — the variable still resolves correctly when a request runs, it just isn't sitting visible in a list for anyone glancing at your screen.

                    ## Where this saves the most time

                    - Switching between dev, staging and production without editing a single request.
                    - Sharing a collection with a teammate whose base URL or credentials differ from yours — they set their own environment, the requests don't change.
                    - Chaining requests where one response feeds the next, via `pm.environment.set(...)` in a test script.

                    See the [scripting reference](/docs/scripting) for the full set of `pm.*` calls available to a script.
                    MD,
            ],
        ];

        foreach ($posts as $post) {
            Post::updateOrCreate(
                ['slug' => $post['slug']],
                [...$post, 'published_at' => $publishedAt],
            );
        }
    }
}
