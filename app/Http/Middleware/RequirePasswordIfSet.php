<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\RequirePassword as BaseRequirePassword;
use Illuminate\Http\Request;

/**
 * Confirming a password only makes sense if the user has something to
 * confirm with. An OAuth-only account with no password and no passkey would
 * otherwise be locked out of every password.confirm-gated route (including
 * the Security settings page where they'd go to set a password) with no way
 * out, since /user/confirm-password itself has nothing to validate against.
 */
class RequirePasswordIfSet extends BaseRequirePassword
{
    protected function shouldConfirmPassword($request, $passwordTimeoutSeconds = null)
    {
        $user = $request->user();

        if ($user && $user->password === null && ! $user->passkeys()->exists()) {
            return false;
        }

        return parent::shouldConfirmPassword($request, $passwordTimeoutSeconds);
    }
}
