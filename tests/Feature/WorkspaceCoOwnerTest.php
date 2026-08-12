<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Collection;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A co-owner is an owner in every respect except two: they cannot delete the
 * workspace, and they cannot demote or remove a fellow co-owner (or the owner).
 */
class WorkspaceCoOwnerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Workspace}
     */
    private function workspaceWithCoOwner(): array
    {
        $owner = User::factory()->create();
        $coOwner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($coOwner, ['role' => WorkspaceRole::CoOwner->value]);

        return [$owner, $coOwner, $workspace];
    }

    public function test_owner_can_invite_someone_as_a_co_owner(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => $invitee->email,
                'role' => 'co_owner',
            ])
            ->assertOk()
            ->assertJsonFragment(['email' => $invitee->email, 'role' => 'co_owner']);

        $this->assertSame(WorkspaceRole::CoOwner, $workspace->fresh()->roleFor($invitee));
    }

    public function test_co_owner_can_invite_other_people(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();
        $invitee = User::factory()->create();

        $this->actingAs($coOwner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => $invitee->email,
                'role' => 'editor',
            ])
            ->assertOk();

        $this->assertSame(WorkspaceRole::Editor, $workspace->fresh()->roleFor($invitee));
    }

    public function test_co_owner_can_appoint_another_co_owner(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();
        $invitee = User::factory()->create();

        $this->actingAs($coOwner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => $invitee->email,
                'role' => 'co_owner',
            ])
            ->assertOk();

        $this->assertSame(WorkspaceRole::CoOwner, $workspace->fresh()->roleFor($invitee));
    }

    public function test_co_owner_can_revoke_a_pending_invitation(): void
    {
        Notification::fake();

        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();
        $invitation = $workspace->invitations()->create([
            'email' => 'pending@example.com',
            'role' => WorkspaceRole::Viewer->value,
            'invited_by_id' => $coOwner->id,
        ]);

        $this->actingAs($coOwner)
            ->deleteJson(route('api.invitations.destroy', [$workspace, $invitation]))
            ->assertNoContent();

        $this->assertSame(0, WorkspaceInvitation::count());
    }

    public function test_co_owner_can_change_an_editors_role_and_remove_them(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();
        $editor = User::factory()->create();
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($coOwner)
            ->patchJson(route('api.members.update-role', [$workspace, $editor]), ['role' => 'viewer'])
            ->assertOk();

        $this->assertSame(WorkspaceRole::Viewer, $workspace->fresh()->roleFor($editor));

        $this->actingAs($coOwner)
            ->deleteJson(route('api.members.destroy', [$workspace, $editor]))
            ->assertNoContent();

        $this->assertNull($workspace->fresh()->roleFor($editor));
    }

    public function test_co_owner_can_edit_workspace_content(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($coOwner)
            ->postJson(route('api.collections.store', $workspace), ['name' => 'Co-owned'])
            ->assertOk();

        $this->assertSame(1, Collection::count());
    }

    public function test_co_owner_can_rename_the_workspace(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($coOwner)
            ->patch(route('workspaces.update', $workspace), ['name' => 'Renamed'])
            ->assertRedirect();

        $this->assertSame('Renamed', $workspace->fresh()->name);
    }

    public function test_co_owner_cannot_delete_the_workspace(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($coOwner)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertForbidden();

        $this->assertNotNull($workspace->fresh());
    }

    public function test_co_owner_cannot_change_the_owners_role(): void
    {
        [$owner, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($coOwner)
            ->patchJson(route('api.members.update-role', [$workspace, $owner]), ['role' => 'viewer'])
            ->assertStatus(422);

        $this->assertSame(WorkspaceRole::Owner, $workspace->fresh()->roleFor($owner));
    }

    public function test_co_owner_cannot_remove_the_owner(): void
    {
        [$owner, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($coOwner)
            ->deleteJson(route('api.members.destroy', [$workspace, $owner]))
            ->assertStatus(422);

        $this->assertSame(WorkspaceRole::Owner, $workspace->fresh()->roleFor($owner));
    }

    public function test_co_owner_cannot_demote_another_co_owner(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();
        $peer = User::factory()->create();
        $workspace->members()->attach($peer, ['role' => WorkspaceRole::CoOwner->value]);

        $this->actingAs($coOwner)
            ->patchJson(route('api.members.update-role', [$workspace, $peer]), ['role' => 'viewer'])
            ->assertForbidden();

        $this->assertSame(WorkspaceRole::CoOwner, $workspace->fresh()->roleFor($peer));
    }

    public function test_co_owner_cannot_remove_another_co_owner(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();
        $peer = User::factory()->create();
        $workspace->members()->attach($peer, ['role' => WorkspaceRole::CoOwner->value]);

        $this->actingAs($coOwner)
            ->deleteJson(route('api.members.destroy', [$workspace, $peer]))
            ->assertForbidden();

        $this->assertSame(WorkspaceRole::CoOwner, $workspace->fresh()->roleFor($peer));
    }

    public function test_owner_can_demote_a_co_owner(): void
    {
        [$owner, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($owner)
            ->patchJson(route('api.members.update-role', [$workspace, $coOwner]), ['role' => 'editor'])
            ->assertOk();

        $this->assertSame(WorkspaceRole::Editor, $workspace->fresh()->roleFor($coOwner));
    }

    public function test_owner_can_remove_a_co_owner(): void
    {
        [$owner, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($owner)
            ->deleteJson(route('api.members.destroy', [$workspace, $coOwner]))
            ->assertNoContent();

        $this->assertNull($workspace->fresh()->roleFor($coOwner));
    }

    public function test_a_co_owner_can_leave_of_their_own_accord(): void
    {
        [, $coOwner, $workspace] = $this->workspaceWithCoOwner();

        $this->actingAs($coOwner)
            ->deleteJson(route('api.members.destroy', [$workspace, $coOwner]))
            ->assertNoContent();

        $this->assertNull($workspace->fresh()->roleFor($coOwner));
    }

    public function test_editor_still_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($editor)
            ->postJson(route('api.members.store', $workspace), [
                'email' => 'someone@example.com',
                'role' => 'co_owner',
            ])
            ->assertForbidden();
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => 'someone@example.com',
                'role' => 'owner',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_an_invitation_can_be_accepted_as_a_co_owner(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'co@example.com']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $invitation = $workspace->invitations()->create([
            'email' => 'co@example.com',
            'role' => WorkspaceRole::CoOwner->value,
            'invited_by_id' => $owner->id,
        ]);

        $this->actingAs($invitee)
            ->get(route('invitations.accept', $invitation->token))
            ->assertRedirect(route('workspaces.show', $workspace));

        $this->assertSame(WorkspaceRole::CoOwner, $workspace->fresh()->roleFor($invitee));
    }
}
