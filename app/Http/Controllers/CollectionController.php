<?php

namespace App\Http\Controllers;

use App\Actions\ExportCollectionAction;
use App\Actions\ExportOpenApiAction;
use App\Actions\ImportCollectionAction;
use App\Enums\AuthType;
use App\Models\Collection;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CollectionController extends Controller
{
    public function import(Request $request, Workspace $workspace, ImportCollectionAction $action): JsonResponse
    {
        $this->authorize('edit', $workspace);

        // A single top-level rule keeps the whole nested tree intact — adding dot-notated
        // rules for sub-keys (e.g. 'collection.info') would make validated() prune every
        // sibling key (item, variable, ...) that isn't also explicitly listed.
        $data = $request->validate([
            'collection' => ['required', 'array'],
        ]);

        if (! is_array($data['collection']['info'] ?? null) || ! is_string($data['collection']['info']['name'] ?? null)) {
            abort(422, 'Invalid Postman collection: missing info.name.');
        }

        $collection = $action->handle($workspace, $data['collection']);

        return response()->json($collection->load('requests', 'children'));
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
            $parent = Collection::forWorkspace($collection->workspace_id)->find($data['parent_id']);
            abort_unless($parent, 422, 'Target folder not found in this workspace.');

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
