<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WorkspaceSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_an_existing_user_and_they_are_added_immediately(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => $invitee->email,
                'role' => 'editor',
            ])
            ->assertOk()
            ->assertJsonFragment(['email' => $invitee->email, 'role' => 'editor']);

        $this->assertSame(WorkspaceRole::Editor, $workspace->fresh()->roleFor($invitee));
        $this->assertSame(0, WorkspaceInvitation::count());
    }

    public function test_owner_can_invite_a_new_email_and_a_pending_invitation_is_created(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => 'newperson@example.com',
                'role' => 'viewer',
            ])
            ->assertOk()
            ->assertJsonPath('invitations.0.email', 'newperson@example.com');

        $invitation = WorkspaceInvitation::sole();
        $this->assertSame('viewer', $invitation->role->value);
        $this->assertSame($workspace->id, $invitation->workspace_id);

        Notification::assertSentOnDemand(WorkspaceInvitationNotification::class);
    }

    public function test_the_members_payload_exposes_a_shareable_invite_link(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(route('api.members.store', $workspace), [
            'email' => 'linkme@example.com',
            'role' => 'viewer',
        ])->assertOk();

        $invitation = WorkspaceInvitation::sole();

        $this->actingAs($owner)
            ->getJson(route('api.members.index', $workspace))
            ->assertOk()
            ->assertJsonPath(
                'invitations.0.url',
                route('invitations.accept', $invitation->token),
            );
    }

    public function test_editor_cannot_invite_members(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($editor)
            ->postJson(route('api.members.store', $workspace), [
                'email' => 'someone@example.com',
                'role' => 'viewer',
            ])
            ->assertForbidden();
    }

    public function test_cannot_invite_someone_who_is_already_a_member(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($owner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => $editor->email,
                'role' => 'viewer',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_cannot_invite_the_workspace_owner(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.members.store', $workspace), [
                'email' => $owner->email,
                'role' => 'viewer',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_duplicate_invitations_are_rejected(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->postJson(route('api.members.store', $workspace), [
            'email' => 'dupe@example.com',
            'role' => 'viewer',
        ])->assertOk();

        $this->actingAs($owner)->postJson(route('api.members.store', $workspace), [
            'email' => 'dupe@example.com',
            'role' => 'editor',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(1, WorkspaceInvitation::count());
    }

    public function test_invited_user_can_accept_an_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $invitation = $workspace->invitations()->create([
            'email' => 'invitee@example.com',
            'role' => WorkspaceRole::Editor->value,
            'invited_by_id' => $owner->id,
        ]);

        $this->actingAs($invitee)
            ->get(route('invitations.accept', $invitation->token))
            ->assertRedirect(route('workspaces.show', $workspace));

        $this->assertSame(WorkspaceRole::Editor, $workspace->fresh()->roleFor($invitee));
        $this->assertSame(0, WorkspaceInvitation::count());
    }

    public function test_accepting_an_invitation_sent_to_a_different_email_does_not_grant_access(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $invitation = $workspace->invitations()->create([
            'email' => 'intended@example.com',
            'role' => WorkspaceRole::Editor->value,
            'invited_by_id' => $owner->id,
        ]);

        $this->actingAs($stranger)
            ->get(route('invitations.accept', $invitation->token))
            ->assertRedirect(route('workspaces.index'));

        $this->assertNull($workspace->fresh()->roleFor($stranger));
        $this->assertSame(1, WorkspaceInvitation::count());
    }

    public function test_owner_can_change_a_members_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($owner)
            ->patchJson(route('api.members.update-role', [$workspace, $member]), ['role' => 'viewer'])
            ->assertOk();

        $this->assertSame(WorkspaceRole::Viewer, $workspace->fresh()->roleFor($member));
    }

    public function test_the_owners_role_cannot_be_changed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patchJson(route('api.members.update-role', [$workspace, $owner]), ['role' => 'viewer'])
            ->assertStatus(422);

        $this->assertSame(WorkspaceRole::Owner, $workspace->fresh()->roleFor($owner));
    }

    public function test_owner_can_remove_a_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($owner)
            ->deleteJson(route('api.members.destroy', [$workspace, $member]))
            ->assertNoContent();

        $this->assertNull($workspace->fresh()->roleFor($member));
    }

    public function test_a_member_can_remove_themselves(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($member, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($member)
            ->deleteJson(route('api.members.destroy', [$workspace, $member]))
            ->assertNoContent();

        $this->assertNull($workspace->fresh()->roleFor($member));
    }

    public function test_editor_cannot_remove_another_member(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $other = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);
        $workspace->members()->attach($other, ['role' => WorkspaceRole::Viewer->value]);

        $this->actingAs($editor)
            ->deleteJson(route('api.members.destroy', [$workspace, $other]))
            ->assertForbidden();
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->deleteJson(route('api.members.destroy', [$workspace, $owner]))
            ->assertStatus(422);
    }

    public function test_owner_can_revoke_a_pending_invitation(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $invitation = $workspace->invitations()->create([
            'email' => 'revoke-me@example.com',
            'role' => WorkspaceRole::Viewer->value,
            'invited_by_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('api.invitations.destroy', [$workspace, $invitation]))
            ->assertNoContent();

        $this->assertSame(0, WorkspaceInvitation::count());
    }

    public function test_members_index_lists_owner_and_members(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $this->actingAs($editor)
            ->getJson(route('api.members.index', $workspace))
            ->assertOk()
            ->assertJsonCount(2, 'members');
    }
}
