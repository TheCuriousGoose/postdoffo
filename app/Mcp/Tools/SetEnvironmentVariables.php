<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Title('Set environment variables')]
#[Description(
    'Create or update variables in an environment, one call for as many as you like. Existing '
    .'keys are overwritten and new ones added; keys you do not mention are left alone, so this '
    .'never silently drops a variable. Use it to point a collection at a new host, or to store a '
    .'token a login request returns.'
)]
#[IsIdempotent]
class SetEnvironmentVariables extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'environment_id' => $schema->integer()->required(),
            'variables' => $schema->array()
                ->min(1)
                ->items($schema->object([
                    'key' => $schema->string()->description('Referenced in requests as {{key}}.')->required(),
                    'value' => $schema->string(),
                    'is_secret' => $schema->boolean()->description('Encrypted at rest, masked in the UI, and not returned by any read tool.'),
                ]))
                ->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'environment_id' => ['required', 'integer'],
            'variables' => ['required', 'array', 'min:1'],
            'variables.*.key' => ['required', 'string', 'max:255'],
            'variables.*.value' => ['nullable', 'string'],
            'variables.*.is_secret' => ['sometimes', 'boolean'],
        ]);

        $environment = $this->environment((int) $validated['environment_id'], 'edit');

        $written = [];

        DB::transaction(function () use ($environment, $validated, &$written): void {
            foreach ($validated['variables'] as $variable) {
                // updateOrCreate rather than upsert: the value column is an
                // encrypted cast, and a bulk upsert would write the plaintext
                // straight past it.
                $environment->variables()->updateOrCreate(
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
            'written_keys' => $written,
            'environment' => Presenter::environment($environment->fresh()->load('variables')),
        ]);
    }
}
