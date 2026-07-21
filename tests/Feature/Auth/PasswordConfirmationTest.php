<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword'),
        );
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }

    public function test_confirm_password_screen_hides_password_form_and_passkeys_when_user_has_neither()
    {
        $user = User::factory()->create(['password' => null]);

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword')
            ->where('hasPassword', false)
            ->where('hasPasskeys', false),
        );
    }

    public function test_oauth_only_user_without_password_or_passkey_can_still_reach_security_settings()
    {
        $user = User::factory()->create(['password' => null]);

        $response = $this->actingAs($user)->get(route('security.edit'));

        $response->assertOk();
    }
}
