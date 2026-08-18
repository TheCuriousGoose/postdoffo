<?php

namespace App\Mcp;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Authenticates the stdio transport.
 *
 * Over HTTP the `auth:api` middleware has a real request to read the bearer
 * token from. `php artisan mcp:start` has no request at all, so the token
 * arrives in the environment instead and we hand the guard a synthetic request
 * carrying it — the token is validated by exactly the same Passport guard the
 * web transport uses, rather than by a second, weaker path that trusts whoever
 * can set an environment variable.
 */
final class LocalSessionAuthenticator
{
    public const TOKEN_ENV = 'POSTDOFFO_MCP_TOKEN';

    public function authenticate(): User
    {
        $token = trim((string) config('mcp.local_token'));

        if ($token === '') {
            throw new RuntimeException(
                'The MCP stdio transport needs a personal access token. Set '.self::TOKEN_ENV
                .' in the environment of the process running `php artisan mcp:start postdoffo`.'
                .' Create one under Settings → MCP access, or with `php artisan mcp:token`.'
            );
        }

        $request = Request::create('/mcp', 'POST');
        $request->headers->set('Authorization', 'Bearer '.$token);

        // Bound before the guard is first resolved so the guard is constructed
        // around this request; the container's rebinding callback keeps an
        // already-resolved guard in step too.
        app()->instance('request', $request);

        $user = Auth::guard('api')->user();

        if (! $user instanceof User) {
            throw new RuntimeException(
                'The token in '.self::TOKEN_ENV.' is not valid — it may have been revoked or expired.'
                .' Issue a new one with `php artisan mcp:token`.'
            );
        }

        Auth::shouldUse('api');

        return $user;
    }
}
