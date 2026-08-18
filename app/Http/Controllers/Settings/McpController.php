<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mcp\LocalSessionAuthenticator;
use App\Mcp\McpScopes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use RuntimeException;

/**
 * Settings -> MCP access: where a user connects an AI assistant to their account
 * and, more importantly, where they can see what is currently connected and cut
 * it off. Both halves of that matter — an access grant nobody can review is one
 * nobody can revoke.
 */
class McpController extends Controller
{
    public function edit(Request $request, ClientRepository $clients): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Mcp', [
            'serverUrl' => url('/mcp'),
            'tokenEnvName' => LocalSessionAuthenticator::TOKEN_ENV,
            'scopes' => [
                'full' => McpScopes::USE,
                'readOnly' => McpScopes::READ,
            ],
            'personalAccessTokensAvailable' => $this->hasPersonalAccessClient($clients),
            'tokens' => $this->personalAccessTokens($request),
            'connectedApps' => $this->connectedApps($request),
            // The plaintext token, available for exactly one render after it is
            // created. It is never stored anywhere it could be read back.
            'newToken' => $request->session()->get('mcp.new_token'),
        ]);
    }

    /**
     * Issue a personal access token, for MCP clients that run the server locally
     * over stdio rather than going through the OAuth flow.
     */
    public function storeToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'read_only' => ['sometimes', 'boolean'],
        ]);

        $scopes = ($data['read_only'] ?? false) ? [McpScopes::READ] : [McpScopes::USE];

        try {
            $token = $request->user()->createToken($data['name'], $scopes);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages([
                'name' => 'This installation cannot issue personal access tokens yet. '
                    .'An administrator needs to run: php artisan passport:client --personal',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token created.')]);

        return back()->with('mcp.new_token', [
            'name' => $data['name'],
            'value' => $token->accessToken,
            'read_only' => in_array(McpScopes::READ, $scopes, true),
        ]);
    }

    public function destroyToken(Request $request, string $token): RedirectResponse
    {
        $model = $request->user()->tokens()->whereKey($token)->firstOrFail();

        $model->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }

    /**
     * Revoke every token an OAuth client holds for this user — the "disconnect
     * this assistant" action. Revoking one token would leave the client able to
     * mint another from its refresh token, so the whole grant goes.
     */
    public function destroyApp(Request $request, string $client): RedirectResponse
    {
        $tokens = $request->user()->tokens()
            ->where('client_id', $client)
            ->where('revoked', false)
            ->get();

        abort_if($tokens->isEmpty(), 404);

        foreach ($tokens as $token) {
            $token->revoke();
            $token->refreshToken?->revoke();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Application disconnected.')]);

        return back();
    }

    private function hasPersonalAccessClient(ClientRepository $clients): bool
    {
        try {
            $clients->personalAccessClient(
                (string) config('auth.guards.api.provider', 'users')
            );

            return true;
        } catch (RuntimeException) {
            // Passport throws rather than returning null when the signing client
            // is missing. The page stays usable, it just explains why the token
            // form isn't there.
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function personalAccessTokens(Request $request): array
    {
        return $request->user()->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get()
            ->filter(fn (Token $token) => $token->client?->hasGrantType('personal_access') === true)
            ->map(fn (Token $token) => [
                'id' => $token->id,
                'name' => $token->name ?: 'Unnamed token',
                'read_only' => ! in_array(McpScopes::USE, $token->scopes ?? [], true),
                'scopes' => $token->scopes,
                'created_at_diff' => $token->created_at?->diffForHumans(),
                'expires_at_diff' => $token->expires_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /**
     * OAuth clients currently holding a live token for this user, one row per
     * app rather than per token — a client that refreshed ten times is still one
     * connection as far as the person reading this page is concerned.
     *
     * @return array<int, array<string, mixed>>
     */
    private function connectedApps(Request $request): array
    {
        return $request->user()->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get()
            ->filter(fn (Token $token) => $token->client instanceof Client
                && $token->client->hasGrantType('personal_access') === false)
            ->groupBy(fn (Token $token) => (string) $token->client->getKey())
            ->map(fn ($tokens, $clientId) => [
                'client_id' => (string) $clientId,
                'name' => $tokens->first()->client->name,
                'scopes' => $tokens->first()->scopes,
                'read_only' => ! in_array(McpScopes::USE, $tokens->first()->scopes ?? [], true),
                'connected_at_diff' => $tokens->last()->created_at?->diffForHumans(),
                'last_token_at_diff' => $tokens->first()->created_at?->diffForHumans(),
                'expires_at_diff' => $tokens->first()->expires_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }
}
