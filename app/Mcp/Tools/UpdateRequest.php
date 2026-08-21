<?php

namespace App\Mcp\Tools;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Mcp\Presenter;
use App\Mcp\Schemas;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Title('Update request')]
#[Description(
    'Change an existing request, or move it to another collection. Only the arguments you pass '
    .'are written; the rest keep their current values. Note that list-valued arguments replace '
    .'rather than merge — to add one header, send the existing list plus the new row, which '
    .'means reading the request with get_request first. This is also how you attach a test_script '
    .'to a request that does not have one yet.'
)]
#[IsIdempotent]
class UpdateRequest extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()->required(),
            'collection_id' => $schema->string()->description('Move the request into this collection. Must be in the same workspace.'),
            'name' => $schema->string()->max(255),
            'method' => Schemas::method($schema),
            'url' => $schema->string(),
            'headers' => Schemas::keyValueList($schema, 'Replaces the header list entirely.'),
            'query_params' => Schemas::keyValueList($schema, 'Replaces the query parameter list entirely.'),
            'body_type' => Schemas::bodyType($schema),
            'body' => Schemas::body($schema),
            'auth_type' => Schemas::authType($schema),
            'auth' => Schemas::auth($schema),
            'pre_request_script' => Schemas::preRequestScript($schema),
            'test_script' => Schemas::testScript($schema),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string', 'uuid'],
            'collection_id' => ['sometimes', 'string', 'uuid'],
            'name' => ['sometimes', 'string', 'max:255'],
            'method' => ['sometimes', Rule::enum(HttpMethod::class)],
            'url' => ['sometimes', 'nullable', 'string'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'query_params' => ['sometimes', 'nullable', 'array'],
            'body_type' => ['sometimes', Rule::enum(BodyType::class)],
            'body' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'nullable', Rule::enum(AuthType::class)],
            'auth' => ['sometimes', 'nullable', 'array'],
            'pre_request_script' => ['sometimes', 'nullable', 'string'],
            'test_script' => ['sometimes', 'nullable', 'string'],
        ]);

        $apiRequest = $this->apiRequest($validated['request_id'], 'edit');

        unset($validated['request_id']);

        if (array_key_exists('collection_id', $validated)) {
            $target = $this->collection($validated['collection_id'], 'edit');

            if ($target->workspace_id !== $apiRequest->collection->workspace_id) {
                throw ValidationException::withMessages([
                    'collection_id' => 'A request can only be moved within its own workspace.',
                ]);
            }

            $validated['collection_id'] = $target->id;
        }

        // The app stores an unset url as an empty string, not null — matching the
        // controller keeps a request written here indistinguishable from one
        // written in the UI.
        if (array_key_exists('url', $validated) && $validated['url'] === null) {
            $validated['url'] = '';
        }

        $apiRequest->update($validated);

        return $this->json([
            'request' => Presenter::request($apiRequest->fresh()),
        ]);
    }
}
