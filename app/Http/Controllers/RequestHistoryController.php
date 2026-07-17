<?php

namespace App\Http\Controllers;

use App\Models\RequestHistory;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestHistoryController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $history = $workspace->requestHistory()
            ->recent()
            ->paginate(25);

        return response()->json($history);
    }

    public function show(RequestHistory $requestHistory): JsonResponse
    {
        $this->authorize('view', $requestHistory->workspace);

        return response()->json($requestHistory);
    }

    public function destroy(RequestHistory $requestHistory): JsonResponse
    {
        $this->authorize('edit', $requestHistory->workspace);

        $requestHistory->delete();

        return response()->json(status: 204);
    }
}
