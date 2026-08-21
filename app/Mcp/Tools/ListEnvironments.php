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

#[Title('List environments')]
#[Description(
    'The environments in a workspace and the variables each defines. Values marked secret come '
    .'back as null — you can still reference them as {{name}} in a request without seeing them.'
)]
#[IsReadOnly]
#[IsIdempotent]
class ListEnvironments extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'view');

        return $this->json([
            'environments' => $workspace->environments()
                ->with('variables')
                ->orderBy('name')
                ->get()
                ->map(fn ($environment) => Presenter::environment($environment))
                ->all(),
        ]);
    }
}
