<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
        ];

        $workspaces = Workspace::query()
            ->with('owner:id,name,email')
            ->withCount(['collections', 'members'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhereHas('owner', function (Builder $query) use ($term): void {
                            $query->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('admin/workspaces/Index', [
            'workspaces' => $workspaces,
            'filters' => $filters,
        ]);
    }

    public function destroy(Workspace $workspace): RedirectResponse
    {
        $workspace->delete();

        Cache::forget(DashboardController::CACHE_KEY);

        return back();
    }
}
