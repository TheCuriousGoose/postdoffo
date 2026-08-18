# PostDoffo

[![Laravel Forge Site Deployment Status](https://img.shields.io/endpoint?url=https%3A%2F%2Fforge.laravel.com%2Fsite-badges%2F1ca9531f-4db7-4d5d-8019-ea521a89b93f&style=plastic)](https://forge.laravel.com/goose-engineering/forge-1-qh9/3297889)

PostDoffo is a browser-based API client — think Postman, self-hosted. Build requests, organize them into collections and folders, switch between environments, script requests with a small `pm.*` DSL, and share workspaces with teammates.

## Stack

- **Backend:** Laravel 13, [Inertia.js](https://inertiajs.com/), [Fortify](https://laravel.com/docs/fortify) (password auth, 2FA, passkeys, GitHub/Google social login)
- **Frontend:** Vue 3 + TypeScript, Tailwind CSS v4, Pinia
- **Routing:** [Wayfinder](https://github.com/laravel/wayfinder) — generates typed route/action helpers from PHP routes and controllers, so the frontend never hand-writes a URL
- **Build:** Vite

## Features

- Workspaces containing collections, folders, and requests, with role-based sharing and invitations
- Environments and variables, with `{{variable}}` interpolation and inline resolution hints in the editor
- A pre-request/test scripting DSL (`pm.test`, `pm.variables.set`, `pm.response.json`, ...) — a closed grammar evaluated server-side in PHP, not JavaScript. See `/docs/scripting` once the app is running.
- Request history, with full response snapshots fetched on demand rather than loaded up front
- Command palette (`Ctrl+K`) for jumping to requests, and a dialog for exporting collections
- Requests to `.test`/`.local`/`localhost` targets are sent directly from the browser (so they can reach hosts your server can't resolve); everything else is proxied server-side to avoid CORS
- An [MCP](https://modelcontextprotocol.io/) server, so an AI assistant can build collections, write test scripts and run them for you — see below

## Getting started

Requires PHP 8.3+, Composer, Node 18+, and a database (MySQL/MariaDB or SQLite).

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate

npm run dev        # Vite dev server
php artisan serve  # or point a local vhost (Laragon/Valet/etc.) at /public
```

Set `GITHUB_CLIENT_ID`/`GITHUB_CLIENT_SECRET` and/or `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` in `.env` to enable social login. Without them, email/password registration still works.

## MCP server

PostDoffo exposes a [Model Context Protocol](https://modelcontextprotocol.io/) server, so an assistant can do the tedious half of the work: turn a pile of curl commands or a Postman export into a collection, put the varying parts into environment variables, write a `pm.*` test script per request, then run the whole collection and report what failed.

There are 29 tools covering workspaces, collections and folders, requests, environments and variables, history, Postman import and OpenAPI export, plus `execute_request` and `run_collection`. The script grammar is published as an MCP resource (`postdoffo://scripting-reference`) so the assistant writes scripts the evaluator actually accepts.

**An assistant acts as the user it is connected to.** It reaches exactly the workspaces that user is a member of, and their role in each one still decides what it may change — the same `WorkspacePolicy` the web UI runs on. Inviting and removing members is deliberately not exposed.

### Connecting a hosted client (Claude, Cursor, …)

Point the client at `https://your-install/mcp`. It registers itself through OAuth ([Passport](https://laravel.com/docs/passport)) and the user approves it on a consent screen. Approved apps are listed under **Settings → MCP access**, where they can be disconnected.

Self-hosting requires the OAuth signing keys once:

```bash
php artisan passport:keys
php artisan passport:client --personal --name="PostDoffo MCP" --provider=users
```

### Connecting a local client (Claude Code, …)

Local clients run the server as a child process over stdio and authenticate with a personal access token:

```bash
php artisan mcp:token you@example.com --name="Claude Code"   # or --read-only
```

Then register it with the client, for example:

```bash
claude mcp add postdoffo --env POSTDOFFO_MCP_TOKEN=<token> -- php /path/to/postdoffo/artisan mcp:start postdoffo
```

Tokens carry one of two scopes: `mcp:use` (full access) or `mcp:read`, which lets the assistant read everything but change nothing and send nothing. Both are revocable from **Settings → MCP access**.

## Development

```bash
php artisan test          # PHPUnit
npm run types:check       # vue-tsc
npm run lint               # ESLint
npm run format              # Prettier
```

## Deployment

Deployed via [Laravel Forge](https://forge.laravel.com/) — see the status badge above.
