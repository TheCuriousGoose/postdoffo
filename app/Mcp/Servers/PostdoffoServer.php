<?php

namespace App\Mcp\Servers;

use App\Mcp\LocalSessionAuthenticator;
use App\Mcp\Resources\ScriptingReference;
use App\Mcp\Tools;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('PostDoffo')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    This server exposes an API client — workspaces hold collections, collections hold
    HTTP requests (and nested folders), and a request can carry a test script that
    asserts things about its response.

    You act as the connected user. Everything you can reach is a workspace they are a
    member of, and their role in each one decides what you may change: viewers can read
    but not write, editors and above can write, and only owners can delete a workspace.

    Typical flow when asked to build something out:

      1. `list_workspaces`, then `get_workspace` to see the collection tree.
      2. `create_collection` for a grouping, `create_request` for each endpoint.
         `create_request_from_curl` is the fastest route if you have a curl command
         from someone's API docs.
      3. Put the changing parts in variables — `set_environment_variables` for
         per-environment values like a base URL or a token, `set_workspace_variables`
         for values that hold across environments. Reference them as `{{base_url}}`
         anywhere in a url, header, query param or body.
      4. Give each request a `test_script`. Read the `scripting-reference` resource
         first: the script grammar is a small closed set of `pm.*` calls, not
         JavaScript, and anything outside it is recorded as a failed test.
      5. `run_collection` to fire everything and get one pass/fail report back;
         `execute_request` when you only need a single call.

    Prefer building on what is already there over creating a parallel structure, and
    read a collection's existing requests before adding to it — matching their header
    and auth conventions matters more than any default you would pick.
    MARKDOWN)]
class PostdoffoServer extends Server
{
    /**
     * The whole toolset fits in one page. The package's default of 15 would split
     * it across two, and a client that doesn't follow the cursor would silently
     * never see the execution tools.
     */
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 100;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        // Workspaces
        Tools\ListWorkspaces::class,
        Tools\GetWorkspace::class,
        Tools\CreateWorkspace::class,
        Tools\RenameWorkspace::class,
        Tools\DeleteWorkspace::class,
        Tools\ListWorkspaceMembers::class,

        // Collections and folders
        Tools\CreateCollection::class,
        Tools\UpdateCollection::class,
        Tools\DeleteCollection::class,
        Tools\ImportPostmanCollection::class,
        Tools\ExportCollection::class,

        // Requests
        Tools\GetRequest::class,
        Tools\CreateRequest::class,
        Tools\UpdateRequest::class,
        Tools\DeleteRequest::class,
        Tools\CreateRequestFromCurl::class,
        Tools\GetRequestAsCurl::class,

        // Sending and asserting
        Tools\ExecuteRequest::class,
        Tools\RunCollection::class,

        // Environments and variables
        Tools\ListEnvironments::class,
        Tools\CreateEnvironment::class,
        Tools\ActivateEnvironment::class,
        Tools\DeleteEnvironment::class,
        Tools\SetEnvironmentVariables::class,
        Tools\DeleteEnvironmentVariable::class,
        Tools\SetWorkspaceVariables::class,
        Tools\DeleteWorkspaceVariable::class,

        // History
        Tools\ListRequestHistory::class,
        Tools\GetRequestHistoryEntry::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        ScriptingReference::class,
    ];

    /**
     * The HTTP transport is authenticated by middleware before it ever reaches
     * this class. The stdio transport has no middleware stack to run, so it
     * resolves its token here instead — see LocalSessionAuthenticator.
     *
     * The hasUser() check is what keeps this out of the way when a user has
     * already been established by other means: the test suite acting as someone,
     * or a future in-process caller.
     */
    protected function boot(): void
    {
        if (app()->runningInConsole() && ! Auth::hasUser()) {
            app(LocalSessionAuthenticator::class)->authenticate();
        }
    }
}
