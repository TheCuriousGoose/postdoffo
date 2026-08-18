<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | An "*" may be used to allow all domains.
    |
    */

    'redirect_domains' => array_values(array_filter(
        explode(',', (string) env('MCP_REDIRECT_DOMAINS', '*'))
    )),

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients like Cursor and VS Code use private-use URI
    | schemes (RFC 8252) for redirect callbacks instead of standard schemes
    | like HTTPS. Here, you may list which custom schemes you will allow.
    |
    */

    'custom_schemes' => [
        'claude',
        'cursor',
        'vscode',
        'vscode-insiders',
        'windsurf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | Here you may configure the OAuth authorization server issuer identifier
    | per RFC 8414. This value appears in your protected resource and auth
    | server metadata endpoints. When null, this defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

    /*
    |--------------------------------------------------------------------------
    | Tool Search
    |--------------------------------------------------------------------------
    |
    | Here you may configure the limits enforced during tool search. The maximum
    | number of tool calls limits how many tools each search request can run
    | while the maximum output bytes value caps the size of every result.
    |
    */

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Transport Token
    |--------------------------------------------------------------------------
    |
    | The personal access token the stdio transport authenticates with. The HTTP
    | transport has a request to read a bearer token off; `php artisan mcp:start`
    | has no request, so it reads the token from here instead. Issue one with
    | `php artisan mcp:token {user}`, or from Settings -> MCP access.
    |
    | Note that `php artisan config:cache` freezes whatever is set at cache time.
    |
    */

    'local_token' => env('POSTDOFFO_MCP_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Tool Output Limits
    |--------------------------------------------------------------------------
    |
    | A response body is unbounded — one API listing can run to megabytes — and
    | every character a tool returns is context the assistant re-reads on each
    | following turn. Bodies longer than this are cut off with a note saying so.
    |
    */

    'max_response_characters' => (int) env('MCP_MAX_RESPONSE_CHARACTERS', 20_000),

    /*
    |--------------------------------------------------------------------------
    | Collection Runs
    |--------------------------------------------------------------------------
    |
    | Ceiling on how many requests one run_collection call will fire. Without it
    | a single tool call against a large collection turns into hundreds of
    | outbound requests, and the per-user execution rate limit is spent before
    | the assistant learns anything.
    |
    */

    'max_requests_per_run' => (int) env('MCP_MAX_REQUESTS_PER_RUN', 50),

];
