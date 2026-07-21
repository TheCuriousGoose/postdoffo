<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_the_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_admin_can_promote_a_user_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update-role', $user), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame(UserRole::Admin, $user->fresh()->role);
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update-role', $admin), ['role' => 'user'])
            ->assertForbidden();

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect();

        $this->assertModelMissing($user);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertModelExists($admin);
    }

    public function test_admin_can_delete_any_workspace(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($admin)
            ->delete(route('admin.workspaces.destroy', $workspace))
            ->assertRedirect();

        $this->assertModelMissing($workspace);
    }

    public function test_non_admin_cannot_delete_a_workspace_through_admin_routes(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->delete(route('admin.workspaces.destroy', $workspace))
            ->assertForbidden();

        $this->assertModelExists($workspace);
    }

    public function test_users_index_paginates_results(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(20)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/Index')
                ->where('users.per_page', 12)
                ->where('users.total', 21)
                ->has('users.data', 12)
            );
    }

    public function test_users_index_can_search_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Ada Lovelace']);
        User::factory()->create(['name' => 'Alan Turing']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Lovelace']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Ada Lovelace')
            );
    }

    public function test_users_index_can_filter_by_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'admin']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.role', UserRole::Admin->value)
            );
    }

    public function test_workspaces_index_can_search_by_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Grace Hopper']);
        Workspace::factory()->create(['name' => 'Cobol', 'owner_id' => $owner->id]);
        Workspace::factory()->create(['name' => 'Fortran']);

        $this->actingAs($admin)
            ->get(route('admin.workspaces.index', ['search' => 'Grace']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/workspaces/Index')
                ->has('workspaces.data', 1)
                ->where('workspaces.data.0.name', 'Cobol')
            );
    }
}
