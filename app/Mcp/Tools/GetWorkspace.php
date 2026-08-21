<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Collection;
use App\Models\Request as ApiRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get workspace')]
#[Description(
    'The full contents of one workspace: its collection/folder tree, a summary of every request '
    .'in it, its environments and its workspace-level variables. Read this before adding to a '
    .'workspace, so what you add matches the conventions already in use there.'
)]
#[IsReadOnly]
#[IsIdempotent]
class GetWorkspace extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->description('From list_workspaces.')->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'view');

        $collections = $workspace->collections()
            ->orderBy('order')
            ->orderBy('name')
            ->with(['requests' => fn ($query) => $query->orderBy('order')->orderBy('name')])
            ->get();

        return $this->json([
            'workspace' => Presenter::workspace($workspace, $this->user()),
            'collections' => $this->tree($collections),
            'environments' => $workspace->environments()
                ->with('variables')
                ->orderBy('name')
                ->get()
                ->map(fn ($environment) => Presenter::environment($environment))
                ->all(),
            'workspace_variables' => $workspace->variables()
                ->orderBy('key')
                ->get()
                ->map(fn ($variable) => [
                    'key' => $variable->key,
                    'value' => $variable->is_secret ? null : $variable->value,
                    'is_secret' => $variable->is_secret,
                ])
                ->all(),
        ]);
    }

    /**
     * Rebuilds the parent/child nesting from one flat query, so the whole tree
     * costs a single trip to the database however deep the folders go.
     *
     * @param  EloquentCollection<int, Collection>  $collections
     * @return array<int, array<string, mixed>>
     */
    private function tree(EloquentCollection $collections, ?string $parentId = null): array
    {
        return $collections
            ->where('parent_id', $parentId)
            ->map(fn (Collection $collection) => [
                ...Presenter::collection($collection),
                'requests' => $collection->requests
                    ->map(fn (ApiRequest $apiRequest) => Presenter::requestSummary($apiRequest))
                    ->values()
                    ->all(),
                'children' => $this->tree($collections, $collection->id),
            ])
            ->values()
            ->all();
    }
}
