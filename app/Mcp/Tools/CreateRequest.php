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
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Create request')]
#[Description(
    'Add an HTTP request to a collection. Put anything that changes between environments into a '
    .'{{variable}} rather than hard-coding it — a url of "{{base_url}}/users" works against '
    .'staging and production, "https://api.example.com/users" only ever works against one. '
    .'Include a test_script so the request asserts something; read the "scripting-reference" '
    .'resource for the grammar first.'
)]
class CreateRequest extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'collection_id' => $schema->integer()->description('The collection or folder to add it to.')->required(),
            'name' => $schema->string()->max(255)->description('What the request does, e.g. "Create invoice".')->required(),
            'method' => Schemas::method($schema)->required(),
            'url' => $schema->string()->description('May contain {{variable}} references, e.g. "{{base_url}}/invoices/{{invoice_id}}".')->required(),
            'headers' => Schemas::keyValueList($schema, 'Headers for this request, on top of any inherited from its collection.'),
            'query_params' => Schemas::keyValueList($schema, 'Query string parameters.'),
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
            'collection_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'method' => ['required', Rule::enum(HttpMethod::class)],
            'url' => ['required', 'string'],
            'headers' => ['nullable', 'array'],
            'query_params' => ['nullable', 'array'],
            'body_type' => ['sometimes', Rule::enum(BodyType::class)],
            'body' => ['nullable', 'array'],
            'auth_type' => ['nullable', Rule::enum(AuthType::class)],
            'auth' => ['nullable', 'array'],
            'pre_request_script' => ['nullable', 'string'],
            'test_script' => ['nullable', 'string'],
        ]);

        $collection = $this->collection((int) $validated['collection_id'], 'edit');

        unset($validated['collection_id']);

        $apiRequest = $collection->requests()->create([
            ...$validated,
            'body_type' => $validated['body_type'] ?? BodyType::None->value,
            'order' => $collection->requests()->count(),
        ]);

        return $this->json([
            'request' => Presenter::request($apiRequest),
        ]);
    }
}
