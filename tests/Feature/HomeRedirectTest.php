<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_with_no_workspaces_is_redirected_to_the_workspace_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('workspaces.index'));
    }

    public function test_user_with_an_accessible_last_workspace_is_redirected_there(): void
    {
        $user = User::factory()->create();
        $other = Workspace::factory()->create(['owner_id' => $user->id]);
        $lastWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $user->last_workspace_id = $lastWorkspace->id;
        $user->save();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('workspaces.show', $lastWorkspace));
        $this->assertNotEquals($other->id, $lastWorkspace->id);
    }

    public function test_user_falls_back_to_most_recently_updated_workspace_when_last_workspace_is_inaccessible(): void
    {
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $noLongerAMember = Workspace::factory()->create(['owner_id' => $owner->id]);
        $noLongerAMember->members()->attach($user->id, ['role' => 'editor']);
        $user->last_workspace_id = $noLongerAMember->id;
        $user->save();
        $noLongerAMember->members()->detach($user->id);

        $recent = Workspace::factory()->create(['owner_id' => $user->id, 'updated_at' => now()]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('workspaces.show', $recent));
        $this->assertSame($recent->id, $user->fresh()->last_workspace_id);
    }
}
