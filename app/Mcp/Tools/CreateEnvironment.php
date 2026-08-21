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

#[Title('Create environment')]
#[Description(
    'Create an environment — a named set of variables, typically one per deployment: Local, '
    .'Staging, Production. Requests reference them as {{base_url}} and resolve against whichever '
    .'environment is active, so the same collection can be run anywhere. Mark tokens and '
    .'passwords as secret: they are encrypted at rest and never read back out through MCP.'
)]
class CreateEnvironment extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'name' => $schema->string()->max(255)->description('e.g. "Staging".')->required(),
            'activate' => $schema->boolean()->description('Make this the active environment straight away. Defaults to false.'),
            'variables' => $schema->array()
                ->items($schema->object([
                    'key' => $schema->string()->required(),
                    'value' => $schema->string(),
                    'is_secret' => $schema->boolean()->description('Masked in the UI and withheld from MCP output once written.'),
                ]))
                ->description('The variables to seed it with.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'activate' => ['sometimes', 'boolean'],
            'variables' => ['sometimes', 'array'],
            'variables.*.key' => ['required', 'string', 'max:255'],
            'variables.*.value' => ['nullable', 'string'],
            'variables.*.is_secret' => ['sometimes', 'boolean'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'edit');
        $activate = $validated['activate'] ?? false;

        $environment = DB::transaction(function () use ($workspace, $validated, $activate) {
            $environment = $workspace->environments()->create([
                'name' => $validated['name'],
                'is_active' => $activate,
            ]);

            if ($activate) {
                // Exactly one environment is active at a time, the same
                // invariant the activate endpoint maintains.
                Environment::forWorkspace($workspace->id)
                    ->whereKeyNot($environment->id)
                    ->update(['is_active' => false]);
            }

            foreach ($validated['variables'] ?? [] as $variable) {
                $environment->variables()->create([
                    'key' => $variable['key'],
                    'value' => $variable['value'] ?? null,
                    'is_secret' => $variable['is_secret'] ?? false,
                ]);
            }

            return $environment;
        });

        return $this->json([
            'environment' => Presenter::environment($environment->load('variables')),
        ]);
    }
}
