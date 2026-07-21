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

## Development

```bash
php artisan test          # PHPUnit
npm run types:check       # vue-tsc
npm run lint               # ESLint
npm run format              # Prettier
```

## Deployment

Deployed via [Laravel Forge](https://forge.laravel.com/) — see the status badge above.
