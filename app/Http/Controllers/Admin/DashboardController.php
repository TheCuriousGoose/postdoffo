<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Request as ApiRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'users' => User::count(),
                'admins' => User::where('role', UserRole::Admin)->count(),
                'workspaces' => Workspace::count(),
                'collections' => Collection::count(),
                'requests' => ApiRequest::count(),
            ],
            'recentUsers' => User::orderByDesc('created_at')
                ->limit(8)
                ->get(['id', 'name', 'email', 'role', 'created_at']),
        ]);
    }
}
