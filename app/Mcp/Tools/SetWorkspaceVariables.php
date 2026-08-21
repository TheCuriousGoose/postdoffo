<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Title('Set workspace variables')]
#[Description(
    'Create or update workspace-level variables — the lowest-precedence layer, applied whichever '
    .'environment is active. Use these for values that genuinely do not vary between '
    .'environments (an API version, a shared header value); anything that differs per deployment '
    .'belongs in an environment instead, where the same key can hold a different value. '
    .'Keys you do not mention are left alone.'
)]
#[IsIdempotent]
class SetWorkspaceVariables extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'variables' => $schema->array()
                ->min(1)
                ->items($schema->object([
                    'key' => $schema->string()->required(),
                    'value' => $schema->string(),
                    'is_secret' => $schema->boolean(),
                ]))
                ->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'variables' => ['required', 'array', 'min:1'],
            'variables.*.key' => ['required', 'string', 'max:255'],
            'variables.*.value' => ['nullable', 'string'],
            'variables.*.is_secret' => ['sometimes', 'boolean'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'edit');

        $written = [];

        DB::transaction(function () use ($workspace, $validated, &$written): void {
            foreach ($validated['variables'] as $variable) {
                $workspace->variables()->updateOrCreate(
                    ['key' => $variable['key']],
                    [
                        'value' => $variable['value'] ?? null,
                        'is_secret' => $variable['is_secret'] ?? false,
                    ],
                );

                $written[] = $variable['key'];
            }
        });

        return $this->json([
            'workspace_id' => $workspace->id,
            'written_keys' => $written,
            'workspace_variables' => $workspace->variables()
                ->orderBy('key')
                ->get()
                ->map(fn ($variable) => [
                    'key' => $variable->key,
                    'value' => $variable->is_secret ? null : $variable->value,
                    'is_secret' => $variable->is_secret,
                ])
                ->all(),
        ]);
    }
}
