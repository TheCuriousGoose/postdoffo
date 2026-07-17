<?php

namespace App\Http\Controllers;

use App\Actions\ExecuteRequestAction;
use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request as ApiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
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

        $apiRequest->update($data);

        return response()->json($apiRequest->fresh());
    }

    public function destroy(ApiRequest $apiRequest): JsonResponse
    {
        $this->authorize('edit', $apiRequest->collection->workspace);

        $apiRequest->delete();

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
