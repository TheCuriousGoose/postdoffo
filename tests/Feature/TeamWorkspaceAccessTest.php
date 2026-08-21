<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Enums\WorkspaceRole;
use App\Models\Collection;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The point of a team over a plain directory: joining it grants access to
 * every workspace it owns, without a separate per-workspace invite.
 */
class TeamWorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_workspace_created_inside_a_team_is_owned_by_the_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('workspaces.store'), ['name' => 'Org Workspace', 'team_id' => $team->id])
            ->assertRedirect();

        $workspace = Workspace::sole();
        $this->assertSame($team->id, $workspace->team_id);
    }

    public function test_a_team_member_gets_editor_level_access_to_every_team_workspace_without_an_explicit_invite(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'team_id' => $team->id]);

        $this->assertSame(WorkspaceRole::Editor, $workspace->roleFor($member));

        $this->actingAs($member)
            ->postJson(route('api.collections.store', $workspace), ['name' => 'From a team member'])
            ->assertOk();

        $this->assertSame(1, Collection::count());
    }

    public function test_a_team_admin_gets_co_owner_level_access_including_member_management(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'team_id' => $team->id]);

        $this->assertSame(WorkspaceRole::CoOwner, $workspace->roleFor($admin));

        // Co-owner-equivalent: can manage workspace members...
        $this->actingAs($admin)
            ->postJson(route('api.members.store', $workspace), ['email' => 'x@example.com', 'role' => 'viewer'])
            ->assertOk();

        // ...but still cannot delete the workspace itself (owner-only, unrelated
        // to whether the "owner" access came from a team).
        $this->actingAs($admin)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertForbidden();
    }

    public function test_a_non_team_member_has_no_access_to_the_teams_workspace(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'team_id' => $team->id]);

        $this->assertNull($workspace->roleFor($stranger));

        $this->actingAs($stranger)
            ->get(route('workspaces.show', $workspace))
            ->assertForbidden();
    }

    public function test_an_explicit_workspace_invite_wins_over_a_lower_team_role(): void
    {
        $owner = User::factory()->create();
        $person = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($person, ['role' => TeamRole::Member->value]); // -> Editor

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'team_id' => $team->id]);
        $workspace->members()->attach($person, ['role' => WorkspaceRole::CoOwner->value]);

        $this->assertSame(WorkspaceRole::CoOwner, $workspace->roleFor($person));
    }

    public function test_the_workspace_owner_can_move_it_into_a_team_they_belong_to(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('workspaces.update-team', $workspace), ['team_id' => $team->id])
            ->assertRedirect();

        $this->assertSame($team->id, $workspace->fresh()->team_id);
    }

    public function test_moving_a_workspace_into_a_team_requires_membership_in_that_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('workspaces.update-team', $workspace), ['team_id' => $team->id])
            ->assertForbidden();

        $this->assertNull($workspace->fresh()->team_id);
    }

    public function test_a_co_owner_cannot_move_the_workspace_between_teams(): void
    {
        $owner = User::factory()->create();
        $coOwner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($coOwner, ['role' => TeamRole::Member->value]);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($coOwner, ['role' => WorkspaceRole::CoOwner->value]);

        $this->actingAs($coOwner)
            ->patch(route('workspaces.update-team', $workspace), ['team_id' => $team->id])
            ->assertForbidden();
    }

    public function test_the_owner_can_move_a_workspace_back_out_to_standalone(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'team_id' => $team->id]);

        $this->actingAs($owner)
            ->patch(route('workspaces.update-team', $workspace), ['team_id' => null])
            ->assertRedirect();

        $this->assertNull($workspace->fresh()->team_id);
    }

    public function test_deleting_a_team_detaches_its_workspaces_rather_than_destroying_them(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'team_id' => $team->id]);

        $this->actingAs($owner)->delete(route('teams.destroy', $team))->assertRedirect();

        $this->assertNotNull($workspace->fresh());
        $this->assertNull($workspace->fresh()->team_id);
    }

    public function test_workspaces_index_includes_team_owned_workspaces(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        Workspace::factory()->create(['owner_id' => $owner->id, 'team_id' => $team->id]);

        $response = $this->actingAs($member)->get(route('workspaces.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('workspaces/Index')
            ->has('workspaces', 1)
        );
    }
}
