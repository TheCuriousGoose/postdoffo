<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get request')]
#[Description(
    'One request in full — headers, query params, body, auth and both scripts. get_workspace '
    .'only returns a summary of each request, so read this before editing one, otherwise an '
    .'update that omits a field will look like it wiped something you never saw.'
)]
#[IsReadOnly]
#[IsIdempotent]
class GetRequest extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string', 'uuid'],
        ]);

        $apiRequest = $this->apiRequest($validated['request_id'], 'view');

        return $this->json([
            'request' => Presenter::request($apiRequest),
        ]);
    }
}
