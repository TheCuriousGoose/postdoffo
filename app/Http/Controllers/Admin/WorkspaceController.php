<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(): Response
    {
        $workspaces = Workspace::with('owner:id,name,email')
            ->withCount(['collections', 'members'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function destroy(Workspace $workspace): RedirectResponse
    {
        $workspace->delete();

        return back();
    }
}
