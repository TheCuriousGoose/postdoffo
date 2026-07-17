<?php

namespace App\Http\Controllers;

use App\Actions\ImportCollectionAction;
use App\Models\Collection;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        ]);

        if (! empty($data['parent_id'])) {
            $parent = Collection::forWorkspace($workspace->id)->findOrFail((int) $data['parent_id']);
            $data['parent_id'] = $parent->id;
        }

        $collection = $workspace->collections()->create([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'variables' => $data['variables'] ?? null,
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
        ]);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] === $collection->id) {
            abort(422, 'A collection cannot be its own parent.');
        }

        $collection->update($data);

        return response()->json($collection);
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $this->authorize('edit', $collection->workspace);

        $collection->delete();

        return response()->json(status: 204);
    }
}
