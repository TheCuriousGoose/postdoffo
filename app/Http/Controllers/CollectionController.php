<?php

namespace App\Http\Controllers;

use App\Actions\ExportCollectionAction;
use App\Actions\ExportOpenApiAction;
use App\Actions\ImportCollectionAction;
use App\Actions\ImportEnvironmentAction;
use App\Enums\AuthType;
use App\Models\Collection;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    /**
     * Import a Postman export. The endpoint accepts three shapes and routes each
     * to the right place, keyed off the payload rather than a separate endpoint
     * per type:
     *   - an environment export (`_postman_variable_scope`) -> a new Environment
     *     in this workspace;
     *   - a bundle (a list of collections/environments, or `{collections, environments}`)
     *     -> a brand-new workspace holding them all;
     *   - a single collection (the common case) -> a root collection here.
     */
    public function import(
        Request $request,
        Workspace $workspace,
        ImportCollectionAction $action,
        ImportEnvironmentAction $environmentAction,
    ): JsonResponse {
        $this->authorize('edit', $workspace);

        // A single top-level rule keeps the whole nested tree intact — adding dot-notated
        // rules for sub-keys (e.g. 'collection.info') would make validated() prune every
        // sibling key (item, variable, ...) that isn't also explicitly listed.
        $data = $request->validate([
            'collection' => ['required', 'array'],
        ]);

        $payload = $data['collection'];

        if ($this->isEnvironmentExport($payload)) {
            $environment = $environmentAction->handle($workspace, $payload);

            return response()->json([
                'type' => 'environment',
                'environment' => $environment->load('variables'),
            ]);
        }

        if ($this->isBundle($payload)) {
            $newWorkspace = $this->importBundle($request->user(), $payload, $action, $environmentAction);

            return response()->json([
                'type' => 'workspace',
                'workspace_id' => $newWorkspace->id,
                'name' => $newWorkspace->name,
            ]);
        }

        if (! is_array($payload['info'] ?? null) || ! is_string($payload['info']['name'] ?? null)) {
            abort(422, 'Invalid Postman collection: missing info.name.');
        }

        $collection = $action->handle($workspace, $payload);

        // Unwrapped for backwards compatibility — callers predate the typed
        // envelope above and read the collection fields off the response root.
        return response()->json($collection->load('requests', 'children'));
    }

    /**
     * A Postman environment export: the tell-tale `_postman_variable_scope`, or
     * (for hand-rolled files) a `values` array with no collection markers.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function isEnvironmentExport(array $payload): bool
    {
        if (($payload['_postman_variable_scope'] ?? null) === 'environment') {
            return true;
        }

        return is_array($payload['values'] ?? null)
            && ! isset($payload['info'])
            && ! isset($payload['item']);
    }

    /**
     * A multi-collection bundle: either a plain list of collection/environment
     * objects, or a `{collections: [...], environments: [...]}` wrapper. A lone
     * collection is an associative array (info/item keys), so it isn't a list.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function isBundle(array $payload): bool
    {
        if (is_array($payload['collections'] ?? null)) {
            return true;
        }

        return array_is_list($payload) && $payload !== [];
    }

    /**
     * Create a new workspace owned by the importer and fill it with every
     * collection and environment found in the bundle.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function importBundle(
        User $user,
        array $payload,
        ImportCollectionAction $action,
        ImportEnvironmentAction $environmentAction,
    ): Workspace {
        [$collections, $environments] = $this->splitBundle($payload);

        $name = is_string($payload['name'] ?? null) && $payload['name'] !== ''
            ? $payload['name']
            : 'Imported Workspace';

        $workspace = new Workspace(['name' => $name]);
        $workspace->owner_id = $user->id;
        $workspace->save();

        foreach ($collections as $collection) {
            if (is_array($collection) && is_string($collection['info']['name'] ?? null)) {
                $action->handle($workspace, $collection);
            }
        }

        foreach ($environments as $environment) {
            if (is_array($environment)) {
                $environmentAction->handle($workspace, $environment);
            }
        }

        return $workspace;
    }

    /**
     * Normalise either bundle shape into [collections, environments] lists.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array{0: array<int, mixed>, 1: array<int, mixed>}
     */
    private function splitBundle(array $payload): array
    {
        if (is_array($payload['collections'] ?? null)) {
            return [
                $payload['collections'],
                is_array($payload['environments'] ?? null) ? $payload['environments'] : [],
            ];
        }

        $collections = [];
        $environments = [];

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if ($this->isEnvironmentExport($entry)) {
                $environments[] = $entry;
            } elseif (is_array($entry['info'] ?? null)) {
                $collections[] = $entry;
            }
        }

        return [$collections, $environments];
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('edit', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:collections,id'],
            'variables' => ['nullable', 'array'],
            'headers' => ['nullable', 'array'],
            'auth_type' => ['nullable', Rule::enum(AuthType::class)],
            'auth' => ['nullable', 'array'],
        ]);

        if (! empty($data['parent_id'])) {
            $parent = Collection::forWorkspace($workspace->id)->findOrFail((int) $data['parent_id']);
            $data['parent_id'] = $parent->id;
        }

        $collection = $workspace->collections()->create([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'variables' => $data['variables'] ?? null,
            'headers' => $data['headers'] ?? null,
            'auth_type' => $data['auth_type'] ?? null,
            'auth' => $data['auth'] ?? null,
            'order' => $workspace->collections()->where('parent_id', $data['parent_id'] ?? null)->count(),
        ]);

        return response()->json($collection);
    }

    public function update(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('edit', $collection->workspace);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:collections,id'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'variables' => ['sometimes', 'nullable', 'array'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'nullable', Rule::enum(AuthType::class)],
            'auth' => ['sometimes', 'nullable', 'array'],
        ]);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            if ($data['parent_id'] === $collection->id) {
                abort(422, 'A collection cannot be its own parent.');
            }

            // exists:collections,id alone doesn't scope by workspace, so without this
            // a crafted request could reparent a collection under another workspace's
            // tree entirely.
            $parent = Collection::forWorkspace($collection->workspace_id)->find((int) $data['parent_id']);
            abort_unless($parent !== null, 422, 'Target folder not found in this workspace.');

            $this->assertNotDescendant($collection, $parent);
        }

        $collection->update($data);

        return response()->json($collection);
    }

    /**
     * Guards against a drag-and-drop move creating a cycle (dragging a folder
     * into one of its own subfolders), which would otherwise make the tree
     * unreachable from its root.
     */
    private function assertNotDescendant(Collection $collection, Collection $candidateParent): void
    {
        $current = $candidateParent;

        while ($current !== null) {
            if ($current->id === $collection->id) {
                abort(422, 'Cannot move a folder into its own subfolder.');
            }

            $current = $current->parent_id !== null ? Collection::find($current->parent_id) : null;
        }
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $this->authorize('edit', $collection->workspace);

        $collection->delete();

        return response()->json(status: 204);
    }

    /**
     * Persists a drag-and-drop reorder of the collections/folders that share
     * a parent (root collections when parent_id is null). ids not actually
     * belonging to this workspace+parent are silently dropped rather than
     * rejected, so a stale client-side list can't reorder (or touch) rows it
     * doesn't own.
     */
    public function reorder(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('edit', $workspace);

        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:collections,id'],
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $validIds = Collection::forWorkspace($workspace->id)
            ->where('parent_id', $data['parent_id'] ?? null)
            ->whereIn('id', $data['ordered_ids'])
            ->pluck('id');

        $orderedIds = array_values(array_intersect($data['ordered_ids'], $validIds->all()));

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $order => $id) {
                Collection::whereKey($id)->update(['order' => $order]);
            }
        });

        return response()->json(status: 204);
    }

    /**
     * Export a collection as a Postman v2.1 or OpenAPI 3.0 file. Named
     * "download" rather than "export" so the generated JS route helper
     * isn't a reserved word.
     */
    public function download(
        Request $request,
        Collection $collection,
        ExportCollectionAction $postman,
        ExportOpenApiAction $openApi
    ): JsonResponse {
        $this->authorize('view', $collection->workspace);

        $data = $request->validate([
            'format' => ['sometimes', Rule::in(['postman', 'openapi'])],
        ]);

        $slug = Str::slug($collection->name) ?: 'collection';

        if (($data['format'] ?? 'postman') === 'openapi') {
            return response()->json($openApi->handle($collection))
                ->header('Content-Disposition', 'attachment; filename="'.$slug.'.openapi.json"');
        }

        return response()->json($postman->handle($collection))
            ->header('Content-Disposition', 'attachment; filename="'.$slug.'.postman_collection.json"');
    }
}
