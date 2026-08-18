<?php

namespace App\Http\Controllers\Settings;

use App\Actions\StoreUserAvatarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /**
     * Stream a user's profile picture.
     *
     * The file name is part of the URL and is checked against the one on record,
     * so a replaced picture stops being reachable the moment it's replaced — and
     * nothing but the current file can be pulled out of the avatar directory.
     */
    public function show(Request $request, User $user, string $file): StreamedResponse
    {
        abort_if($user->avatar_path === null || basename($user->avatar_path) !== $file, 404);

        return Storage::disk(User::AVATAR_DISK)->response($user->avatar_path, headers: [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Store a newly uploaded profile picture, replacing any current one.
     */
    public function store(AvatarUpdateRequest $request, StoreUserAvatarAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->picture());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile picture updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Drop the user's profile picture and fall back to their initials.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->deleteAvatarFile();
        $user->forceFill(['avatar_path' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile picture removed.')]);

        return to_route('profile.edit');
    }
}
