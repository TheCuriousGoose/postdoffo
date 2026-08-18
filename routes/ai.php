<?php

use App\Mcp\Servers\PostdoffoServer;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;

/*
|--------------------------------------------------------------------------
| MCP Server
|--------------------------------------------------------------------------
|
| Two ways in, one server. Remote clients (claude.ai, Claude Desktop, Cursor)
| discover the OAuth endpoints below, register themselves, and send the user
| through a consent screen. A local client instead runs the stdio transport as
| a child process and authenticates with a personal access token.
|
| Either way the request arrives as a Passport token belonging to a real user,
| so every tool runs against that user's workspace roles.
|
*/

Mcp::web('/mcp', PostdoffoServer::class)
    ->middleware([
        'auth:api',
        // Any of, not all of: a full-access token carries mcp:use, a read-only
        // one carries mcp:read, and the write tools tell the two apart
        // themselves (see McpScopes).
        CheckTokenForAnyScope::class.':mcp:use,mcp:read',
    ]);

// Advertises this app as an OAuth protected resource and Passport as its
// authorization server, and accepts the dynamic client registration that MCP
// clients use instead of a pre-shared client id.
Mcp::oauthRoutes();

// php artisan mcp:start postdoffo — the stdio transport, for clients that spawn
// the server as a local process.
Mcp::local('postdoffo', PostdoffoServer::class);
