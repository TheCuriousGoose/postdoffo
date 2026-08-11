<?php

namespace App\Http\Controllers;

use App\Models\RequestCookie;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The cookie jar as a manageable list. Everything here is scoped to the calling
 * user's own cookies — a member can't read, or clear, a colleague's session.
 */
class RequestCookieController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        $cookies = RequestCookie::forJar($workspace->id, $request->user()->id)
            ->unexpired()
            ->orderBy('domain')
            ->orderBy('name')
            ->get();

        return response()->json($cookies);
    }

    public function destroy(Request $request, RequestCookie $requestCookie): JsonResponse
    {
        abort_unless($requestCookie->user_id === $request->user()->id, 403);

        $requestCookie->delete();

        return response()->json(status: 204);
    }

    /** Empties this user's jar for the workspace — the "log out of everything" button. */
    public function clear(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        RequestCookie::forJar($workspace->id, $request->user()->id)->delete();

        return response()->json(status: 204);
    }
}
