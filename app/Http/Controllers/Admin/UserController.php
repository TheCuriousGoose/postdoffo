<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::withCount('ownedWorkspaces')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'email_verified_at', 'created_at']);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot change your own role.');

        $data = $request->validate([
            'role' => ['required', new Enum(UserRole::class)],
        ]);

        $user->update(['role' => $data['role']]);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');

        $user->delete();

        return back();
    }
}
