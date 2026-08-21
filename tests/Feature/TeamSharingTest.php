<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_an_existing_user_and_they_are_added_immediately(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.team-members.store', $team), [
                'email' => $invitee->email,
                'role' => 'member',
            ])
            ->assertOk()
            ->assertJsonFragment(['email' => $invitee->email, 'role' => 'member']);

        $this->assertSame(TeamRole::Member, $team->fresh()->roleFor($invitee));
        $this->assertSame(0, TeamInvitation::count());
    }

    public function test_owner_can_invite_a_new_email_and_a_pending_invitation_is_created(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson(route('api.team-members.store', $team), [
                'email' => 'newperson@example.com',
                'role' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('invitations.0.email', 'newperson@example.com');

        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_member_cannot_invite_others(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->postJson(route('api.team-members.store', $team), [
                'email' => 'someone@example.com',
                'role' => 'member',
            ])
            ->assertForbidden();
    }

    public function test_only_the_owner_can_demote_a_fellow_admin(): void
    {
        $owner = User::factory()->create();
        $adminA = User::factory()->create();
        $adminB = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($adminA, ['role' => TeamRole::Admin->value]);
        $team->members()->attach($adminB, ['role' => TeamRole::Admin->value]);

        $this->actingAs($adminA)
            ->patchJson(route('api.team-members.update-role', [$team, $adminB]), ['role' => 'member'])
            ->assertForbidden();

        $this->actingAs($owner)
            ->patchJson(route('api.team-members.update-role', [$team, $adminB]), ['role' => 'member'])
            ->assertOk();

        $this->assertSame(TeamRole::Member, $team->fresh()->roleFor($adminB));
    }

    public function test_invited_user_can_accept_an_invitation(): void
    {
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $invitation = $team->invitations()->create([
            'email' => 'invitee@example.com',
            'role' => TeamRole::Member->value,
            'invited_by_id' => $owner->id,
        ]);

        $this->actingAs($invitee)
            ->get(route('team-invitations.accept', $invitation->token))
            ->assertRedirect(route('teams.show', $team));

        $this->assertSame(TeamRole::Member, $team->fresh()->roleFor($invitee));
        $this->assertSame(0, TeamInvitation::count());
    }

    public function test_accepting_an_invitation_sent_to_a_different_email_does_not_grant_access(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $invitation = $team->invitations()->create([
            'email' => 'intended@example.com',
            'role' => TeamRole::Member->value,
            'invited_by_id' => $owner->id,
        ]);

        $this->actingAs($stranger)
            ->get(route('team-invitations.accept', $invitation->token))
            ->assertRedirect(route('teams.index'));

        $this->assertNull($team->fresh()->roleFor($stranger));
    }

    public function test_the_owner_cannot_be_removed_and_their_role_cannot_change(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patchJson(route('api.team-members.update-role', [$team, $owner]), ['role' => 'member'])
            ->assertStatus(422);

        $this->actingAs($owner)
            ->deleteJson(route('api.team-members.destroy', [$team, $owner]))
            ->assertStatus(422);
    }

    public function test_a_member_can_leave_of_their_own_accord(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->deleteJson(route('api.team-members.destroy', [$team, $member]))
            ->assertNoContent();

        $this->assertNull($team->fresh()->roleFor($member));
    }
}
