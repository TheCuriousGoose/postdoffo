<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Create workspace')]
#[Description(
    'Create a new workspace, owned by you. Reach for this only when the work genuinely does not '
    .'belong in an existing workspace — a workspace is the sharing boundary, so scattering '
    .'related collections across several of them makes them harder to work with, not easier.'
)]
class CreateWorkspace extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->max(255)->description('What this workspace is for, e.g. "Billing API".')->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $this->assertWritableToken();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->user();

        $workspace = new Workspace(['name' => $validated['name']]);
        $workspace->owner_id = $user->id;
        $workspace->save();

        return $this->json([
            'workspace' => Presenter::workspace($workspace, $user),
        ]);
    }
}
