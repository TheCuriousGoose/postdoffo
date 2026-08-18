<?php

namespace App\Console\Commands;

use App\Mcp\LocalSessionAuthenticator;
use App\Mcp\McpScopes;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('mcp:token
    {user : The ID or email of the user the token acts as}
    {--name=MCP CLI : A label for the token, shown in the user\'s settings}
    {--read-only : Issue a token that can read but not modify anything}')]
#[Description('Issue a personal access token for connecting an MCP client over stdio')]
class McpTokenCommand extends Command
{
    public function handle(): int
    {
        $user = User::where('id', $this->argument('user'))
            ->orWhere('email', $this->argument('user'))
            ->first();

        if (! $user instanceof User) {
            $this->components->error('No user matches ['.$this->argument('user').'].');

            return self::FAILURE;
        }

        $scopes = $this->option('read-only') ? [McpScopes::READ] : [McpScopes::USE];

        try {
            $token = $user->createToken((string) $this->option('name'), $scopes);
        } catch (RuntimeException $e) {
            // Personal access tokens are signed by a dedicated OAuth client that
            // `passport:client --personal` creates. Passport's own message says
            // the client is missing but not how to get one.
            $this->components->error($e->getMessage());
            $this->components->info('Create it with: php artisan passport:client --personal');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info("Token issued for {$user->email} with scope [".implode(', ', $scopes).'].');
        $this->newLine();
        $this->line($token->accessToken);
        $this->newLine();
        $this->components->warn('This is the only time the token is shown. Store it now.');
        $this->newLine();

        $this->line('Point a local MCP client at the stdio server with the token in its environment:');
        $this->newLine();
        $this->line('  '.LocalSessionAuthenticator::TOKEN_ENV.'=<token> php artisan mcp:start postdoffo');
        $this->newLine();
        $this->line('Revoke it any time under Settings -> MCP access.');
        $this->newLine();

        return self::SUCCESS;
    }
}
