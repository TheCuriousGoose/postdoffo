<?php

namespace App\Http\Controllers;

use App\Actions\ExecuteRequestAction;
use App\Actions\PrepareRequestAction;
use App\Actions\RecordClientExecutedRequestAction;
use App\Actions\SendPreparedRequestAction;
use App\DTOs\OutgoingRequestData;
use App\DTOs\PreparedRequestData;
use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request as ApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    /**
     * Full request payload (body, headers, scripts, ...), fetched on demand
     * when a tab opens — the workspace sidebar tree only ships the lightweight
     * summary fields (see WorkspaceController::show()).
     */
    public function show(ApiRequest $apiRequest): JsonResponse
    {
        $this->authorize('view', $apiRequest->collection->workspace);

        return response()->json($apiRequest);
    }

    public function store(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('edit', $collection->workspace);

        $data = $this->validated($request);

        $apiRequest = $collection->requests()->create([
            ...$data,
            'url' => $data['url'] ?? '',
            'order' => $collection->requests()->count(),
        ]);

        return response()->json($apiRequest);
    }

    public function update(Request $request, ApiRequest $apiRequest): JsonResponse
    {
        $this->authorize('edit', $apiRequest->collection->workspace);

        $data = $this->validated($request, partial: true);

        if (array_key_exists('url', $data) && $data['url'] === null) {
            $data['url'] = '';
        }

        if (array_key_exists('collection_id', $data) && $data['collection_id'] !== $apiRequest->collection_id) {
            // exists:collections,id alone doesn't scope by workspace, so without this
            // a crafted request could move a request into another workspace's tree.
            $target = Collection::forWorkspace($apiRequest->collection->workspace_id)->find($data['collection_id']);
            abort_unless($target, 422, 'Target folder not found in this workspace.');
        }

        $apiRequest->update($data);

        return response()->json($apiRequest->fresh());
    }

    public function destroy(ApiRequest $apiRequest): JsonResponse
    {
        $this->authorize('edit', $apiRequest->collection->workspace);

        $apiRequest->delete();

        return response()->json(status: 204);
    }

    /**
     * Persists a drag-and-drop reorder of the requests within one collection.
     * ids not actually belonging to this collection are silently dropped, same
     * as CollectionController::reorder().
     */
    public function reorder(Request $request, Collection $collection): JsonResponse
    {
        $this->authorize('edit', $collection->workspace);

        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer'],
        ]);

        $validIds = ApiRequest::forCollection($collection->id)
            ->whereIn('id', $data['ordered_ids'])
            ->pluck('id');

        $orderedIds = array_values(array_intersect($data['ordered_ids'], $validIds->all()));

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $order => $id) {
                ApiRequest::whereKey($id)->update(['order' => $order]);
            }
        });

        return response()->json(status: 204);
    }

    public function execute(Request $request, ApiRequest $apiRequest, ExecuteRequestAction $action): JsonResponse
    {
        $this->authorize('view', $apiRequest->collection->workspace);

        $environment = null;

        if ($request->filled('environment_id')) {
            $environment = Environment::forWorkspace($apiRequest->collection->workspace_id)
                ->findOrFail($request->integer('environment_id'));
        }

        $result = $action->handle($apiRequest, $request->user(), $environment);

        return response()->json($result->toArray());
    }

    /**
     * Resolves the request (variables, pre-request script, interpolation) without
     * firing it, so the browser can send it directly — used for hosts like .test/
     * .local that only resolve on the developer's own machine, not this server.
     */
    public function prepare(Request $request, ApiRequest $apiRequest, PrepareRequestAction $action): JsonResponse
    {
        $this->authorize('view', $apiRequest->collection->workspace);

        $environment = null;

        if ($request->filled('environment_id')) {
            $environment = Environment::forWorkspace($apiRequest->collection->workspace_id)
                ->findOrFail($request->integer('environment_id'));
        }

        $prepared = $action->handle($apiRequest, $environment);

        return response()->json([
            'outgoing' => [
                'method' => $prepared->outgoing->method->value,
                'url' => $prepared->outgoing->url,
                'headers' => $prepared->outgoing->headers,
                'query_params' => $prepared->outgoing->queryParams,
                'body' => $prepared->outgoing->body,
                'body_type' => $prepared->outgoing->bodyType->value,
            ],
            'variables' => $prepared->variables,
        ]);
    }

    /**
     * Fires an already-prepared request from this server. Used when prepare() turned
     * out not to target a local-only host, so the browser hands the resolved request
     * back here instead of firing it itself.
     */
    public function send(Request $request, ApiRequest $apiRequest, SendPreparedRequestAction $action): JsonResponse
    {
        $this->authorize('view', $apiRequest->collection->workspace);

        $data = $request->validate([
            'outgoing.method' => ['required', Rule::enum(HttpMethod::class)],
            'outgoing.url' => ['required', 'string'],
            'outgoing.headers' => ['sometimes', 'array'],
            'outgoing.query_params' => ['sometimes', 'array'],
            'outgoing.body' => ['nullable'],
            'outgoing.body_type' => ['required', Rule::enum(BodyType::class)],
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['nullable', 'string'],
        ]);

        $prepared = new PreparedRequestData(
            OutgoingRequestData::fromArray($data['outgoing']),
            $data['variables'] ?? [],
        );

        $result = $action->handle($apiRequest, $request->user(), $prepared);

        return response()->json($result->toArray());
    }

    /**
     * Counterpart to prepare(): the browser reports back what happened when it fired
     * the request itself, and we run the test script + record history against that.
     */
    public function record(Request $request, ApiRequest $apiRequest, RecordClientExecutedRequestAction $action): JsonResponse
    {
        $this->authorize('view', $apiRequest->collection->workspace);

        $data = $request->validate([
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['nullable', 'string'],
            'status' => ['nullable', 'integer'],
            'headers' => ['sometimes', 'array'],
            'body' => ['nullable', 'string'],
            'duration_ms' => ['required', 'integer', 'min:0'],
            'error' => ['nullable', 'string'],
        ]);

        $result = $action->handle(
            $apiRequest,
            $request->user(),
            $data['variables'] ?? [],
            $data['status'] ?? null,
            $data['headers'] ?? [],
            $data['body'] ?? null,
            $data['duration_ms'],
            $data['error'] ?? null,
        );

        return response()->json($result->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'method' => [$required, Rule::enum(HttpMethod::class)],
            // Always "nullable", never "required" — a freshly created request
            // legitimately has an empty url the user fills in later, and Laravel's
            // ConvertEmptyStringsToNull middleware turns "" into null before this
            // even runs, so "required"/"present" would reject it either way.
            'url' => ['sometimes', 'nullable', 'string'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'collection_id' => ['sometimes', 'integer', 'exists:collections,id'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'query_params' => ['sometimes', 'nullable', 'array'],
            'body' => ['sometimes', 'nullable', 'array'],
            'body_type' => ['sometimes', Rule::enum(BodyType::class)],
            'auth_type' => ['sometimes', 'nullable', Rule::enum(AuthType::class)],
            'auth' => ['sometimes', 'nullable', 'array'],
            'pre_request_script' => ['sometimes', 'nullable', 'string'],
            'test_script' => ['sometimes', 'nullable', 'string'],
        ]);
    }
}
