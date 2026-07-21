<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'role' => $request->string('role')->toString() ?: 'all',
        ];

        $users = User::query()
            ->withCount('ownedWorkspaces')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when(in_array($filters['role'], ['admin', 'user'], true), function (Builder $query) use ($filters): void {
                $query->where('role', $filters['role']);
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'owned_workspaces_count' => $user->owned_workspaces_count,
                'created_at' => $user->created_at,
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => $filters,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot change your own role.');

        $data = $request->validate([
            'role' => ['required', new Enum(UserRole::class)],
        ]);

        $user->update(['role' => $data['role']]);

        Cache::forget(DashboardController::CACHE_KEY);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        $user->delete();

        Cache::forget(DashboardController::CACHE_KEY);

        return back();
    }
}
