<?php

namespace App\Mcp\Concerns;

use App\Mcp\McpScopes;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request as ApiRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

trait InteractsWithWorkspaces
{
    protected function user(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('No authenticated user is associated with this MCP session.');
        }

        return $user;
    }

    protected function assertWritableToken(): void
    {
        $token = $this->user()->currentAccessToken();

        if ($token === null || $token->can(McpScopes::USE)) {
            return;
        }

        throw new AuthorizationException(
            'This MCP token is read-only ('.McpScopes::READ.'). Reconnect with the '.McpScopes::USE.' scope to make changes.'
        );
    }

    protected function workspace(int $id, string $ability = 'view'): Workspace
    {
        $workspace = Workspace::find($id);

        if ($workspace === null) {
            throw ValidationException::withMessages([
                'workspace_id' => "No workspace with id {$id} exists. Call list_workspaces to see the ones you can reach.",
            ]);
        }

        $this->authorizeWorkspace($ability, $workspace);

        return $workspace;
    }

    protected function collection(int $id, string $ability = 'edit'): Collection
    {
        $collection = Collection::with('workspace')->find($id);

        if ($collection === null) {
            throw ValidationException::withMessages([
                'collection_id' => "No collection with id {$id} exists. Call get_workspace to see the collection tree.",
            ]);
        }

        $this->authorizeWorkspace($ability, $collection->workspace);

        return $collection;
    }

    protected function apiRequest(int $id, string $ability = 'edit'): ApiRequest
    {
        $apiRequest = ApiRequest::with('collection.workspace')->find($id);

        if ($apiRequest === null) {
            throw ValidationException::withMessages([
                'request_id' => "No request with id {$id} exists. Call get_workspace to see the requests in a workspace.",
            ]);
        }

        $this->authorizeWorkspace($ability, $apiRequest->collection->workspace);

        return $apiRequest;
    }

    protected function environment(int $id, string $ability = 'edit'): Environment
    {
        $environment = Environment::with('workspace')->find($id);

        if ($environment === null) {
            throw ValidationException::withMessages([
                'environment_id' => "No environment with id {$id} exists. Call list_environments to see them.",
            ]);
        }

        $this->authorizeWorkspace($ability, $environment->workspace);

        return $environment;
    }

    /**
     * An environment belonging to a specific workspace — used by the execution
     * tools, where passing another workspace's environment would leak its
     * variables into this workspace's requests.
     */
    protected function environmentIn(Workspace $workspace, int $id): Environment
    {
        $environment = Environment::forWorkspace($workspace->id)->find($id);

        if ($environment === null) {
            throw ValidationException::withMessages([
                'environment_id' => "Environment {$id} does not belong to workspace {$workspace->id}.",
            ]);
        }

        return $environment;
    }

    private function authorizeWorkspace(string $ability, Workspace $workspace): void
    {
        if ($ability !== 'view') {
            $this->assertWritableToken();
        }

        Gate::forUser($this->user())->authorize($ability, $workspace);
    }
}
