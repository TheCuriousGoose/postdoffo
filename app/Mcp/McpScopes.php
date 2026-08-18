<?php

namespace App\Mcp;

use Laravel\Mcp\Server\Registrar;

/**
 * OAuth scopes an assistant can hold against the MCP server.
 *
 * These sit *on top of* the workspace roles, they don't replace them: a token
 * never grants more than the user it belongs to already has, so an agent
 * connected to a viewer's account still can't write. What the read-only scope
 * adds is the other direction — letting an owner hand an assistant a token that
 * can look at everything but change nothing, without creating a second account.
 */
final class McpScopes
{
    /**
     * Full access. Named by laravel/mcp, which advertises exactly this scope in
     * the OAuth metadata documents, so clients discovering the server ask for it
     * by name — renaming it would break the handshake.
     */
    public const USE = Registrar::OAUTH_SCOPE;

    /** Look, don't touch: every tool that writes refuses a token holding only this. */
    public const READ = 'mcp:read';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            self::USE => 'Read and modify your workspaces, collections, requests and environments, and send requests on your behalf',
            self::READ => 'Read your workspaces, collections, requests and environments without modifying them',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_keys(self::all());
    }
}
