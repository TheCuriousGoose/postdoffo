<?php

namespace App\Http\Controllers;

use App\Models\Request as ApiRequest;
use App\Models\RequestHistory;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $workspaces = Workspace::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('collections')
            ->orderByDesc('updated_at')
            ->get();

        $workspaceIds = $workspaces->pluck('id');

        $requestCount = ApiRequest::whereHas(
            'collection',
            fn ($query) => $query->whereIn('workspace_id', $workspaceIds)
        )->count();

        $recentHistory = RequestHistory::whereIn('workspace_id', $workspaceIds)
            ->recent()
            ->with('workspace:id,name')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'workspaces' => $workspaces,
            'requestCount' => $requestCount,
            'recentHistory' => $recentHistory,
        ]);
    }
}
