<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceVariable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceVariableController extends Controller
{
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('edit', $workspace);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ]);

        $variable = $workspace->variables()->updateOrCreate(
            ['key' => $data['key']],
            ['value' => $data['value'] ?? null, 'is_secret' => $data['is_secret'] ?? false],
        );

        return response()->json($variable);
    }

    public function update(Request $request, WorkspaceVariable $workspaceVariable): JsonResponse
    {
        $this->authorize('edit', $workspaceVariable->workspace);

        $data = $request->validate([
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'nullable', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ]);

        $workspaceVariable->update($data);

        return response()->json($workspaceVariable);
    }

    public function destroy(WorkspaceVariable $workspaceVariable): JsonResponse
    {
        $this->authorize('edit', $workspaceVariable->workspace);

        $workspaceVariable->delete();

        return response()->json(status: 204);
    }
}
