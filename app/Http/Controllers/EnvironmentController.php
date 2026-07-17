<?php

namespace App\Http\Controllers;

use App\Models\Environment;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnvironmentController extends Controller
{
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('edit', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $environment = $workspace->environments()->create([
            'name' => $data['name'],
            'is_active' => $workspace->environments()->count() === 0,
        ]);

        return response()->json($environment->load('variables'));
    }

    public function update(Request $request, Environment $environment): JsonResponse
    {
        $this->authorize('edit', $environment->workspace);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        $environment->update($data);

        return response()->json($environment->fresh('variables'));
    }

    public function activate(Environment $environment): JsonResponse
    {
        $this->authorize('edit', $environment->workspace);

        DB::transaction(function () use ($environment) {
            Environment::forWorkspace($environment->workspace_id)->active()->update(['is_active' => false]);
            Environment::whereKey($environment->id)->update(['is_active' => true]);
        });

        return response()->json($environment->fresh('variables'));
    }

    public function destroy(Environment $environment): JsonResponse
    {
        $this->authorize('edit', $environment->workspace);

        $environment->delete();

        return response()->json(status: 204);
    }
}
