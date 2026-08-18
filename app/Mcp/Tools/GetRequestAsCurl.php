<?php

namespace App\Mcp\Tools;

use App\Actions\PrepareRequestAction;
use App\Services\CurlCommandGenerator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get request as curl')]
#[Description(
    'The curl command equivalent to a saved request, resolved exactly the way sending it would '
    .'be: variables interpolated, inherited collection headers merged in, auth computed. Useful '
    .'for showing the user what will actually go over the wire, or for handing them something '
    .'they can paste into a terminal. Note the output contains real credentials.'
)]
#[IsReadOnly]
#[IsIdempotent]
class GetRequestAsCurl extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->integer()->required(),
            'environment_id' => $schema->integer()->description('Resolve variables against this environment. Defaults to the workspace\'s active one.'),
        ];
    }

    public function handle(Request $request, PrepareRequestAction $action, CurlCommandGenerator $generator): ResponseFactory
    {
        $validated = $request->validate([
            'request_id' => ['required', 'integer'],
            'environment_id' => ['sometimes', 'integer'],
        ]);

        $apiRequest = $this->apiRequest((int) $validated['request_id'], 'view');

        $environment = isset($validated['environment_id'])
            ? $this->environmentIn($apiRequest->collection->workspace, (int) $validated['environment_id'])
            : null;

        $prepared = $action->handle($apiRequest, $environment);

        return $this->json([
            'request_id' => $apiRequest->id,
            'command' => $generator->generate($prepared->outgoing),
        ]);
    }
}
