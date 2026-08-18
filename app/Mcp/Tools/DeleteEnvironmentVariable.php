<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Title('Delete environment variable')]
#[Description('Remove one variable from an environment. Requests still referencing {{key}} will send the literal text instead.')]
#[IsDestructive]
#[IsIdempotent]
class DeleteEnvironmentVariable extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'environment_id' => $schema->integer()->required(),
            'key' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'environment_id' => ['required', 'integer'],
            'key' => ['required', 'string'],
        ]);

        $environment = $this->environment((int) $validated['environment_id'], 'edit');

        $variable = $environment->variables()->where('key', $validated['key'])->first();

        if ($variable === null) {
            throw ValidationException::withMessages([
                'key' => "Environment {$environment->id} (\"{$environment->name}\") has no variable named \"{$validated['key']}\".",
            ]);
        }

        $variable->delete();

        return $this->json([
            'deleted' => true,
            'environment_id' => $environment->id,
            'key' => $validated['key'],
        ]);
    }
}
