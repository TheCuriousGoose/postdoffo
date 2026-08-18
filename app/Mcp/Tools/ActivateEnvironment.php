<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Environment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Title('Activate environment')]
#[Description(
    'Make an environment the active one for its workspace, which changes what every request in '
    .'it resolves to for everyone using that workspace — including in the app, right now. '
    .'Switching to Production is a bigger deal than it looks: prefer passing environment_id to '
    .'execute_request or run_collection for a one-off run.'
)]
#[IsIdempotent]
class ActivateEnvironment extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'environment_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'environment_id' => ['required', 'integer'],
        ]);

        $environment = $this->environment((int) $validated['environment_id'], 'edit');

        DB::transaction(function () use ($environment): void {
            Environment::forWorkspace($environment->workspace_id)->update(['is_active' => false]);
            $environment->update(['is_active' => true]);
        });

        return $this->json([
            'environment' => Presenter::environment($environment->fresh()->load('variables')),
        ]);
    }
}
