<?php

namespace App\Http\Controllers;

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentVariableController extends Controller
{
    public function store(Request $request, Environment $environment): JsonResponse
    {
        $this->authorize('edit', $environment->workspace);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ]);

        $variable = $environment->variables()->updateOrCreate(
            ['key' => $data['key']],
            ['value' => $data['value'] ?? null, 'is_secret' => $data['is_secret'] ?? false],
        );

        return response()->json($variable);
    }

    public function update(Request $request, EnvironmentVariable $environmentVariable): JsonResponse
    {
        $this->authorize('edit', $environmentVariable->environment->workspace);

        $data = $request->validate([
            'key' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'nullable', 'string'],
            'is_secret' => ['sometimes', 'boolean'],
        ]);

        $environmentVariable->update($data);

        return response()->json($environmentVariable);
    }

    public function destroy(EnvironmentVariable $environmentVariable): JsonResponse
    {
        $this->authorize('edit', $environmentVariable->environment->workspace);

        $environmentVariable->delete();

        return response()->json(status: 204);
    }
}
