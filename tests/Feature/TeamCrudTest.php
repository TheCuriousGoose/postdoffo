<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('teams.store'), ['name' => 'Acme Corp'])
            ->assertRedirect();

        $team = Team::sole();
        $this->assertSame('Acme Corp', $team->name);
        $this->assertSame($user->id, $team->owner_id);
    }

    public function test_owner_can_rename_the_team(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch(route('teams.update', $team), ['name' => 'Renamed'])
            ->assertRedirect();

        $this->assertSame('Renamed', $team->fresh()->name);
    }

    public function test_a_non_member_cannot_view_the_team(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($stranger)
            ->get(route('teams.show', $team))
            ->assertForbidden();
    }

    public function test_a_non_member_cannot_rename_the_team(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($stranger)
            ->patch(route('teams.update', $team), ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_only_the_owner_can_delete_the_team(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($admin, ['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('teams.destroy', $team))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('teams.destroy', $team))
            ->assertRedirect(route('teams.index'));

        $this->assertNull($team->fresh());
    }

    public function test_index_lists_owned_and_member_teams(): void
    {
        $user = User::factory()->create();
        $owned = Team::factory()->create(['owner_id' => $user->id]);
        $memberOf = Team::factory()->create();
        $memberOf->members()->attach($user, ['role' => 'member']);
        Team::factory()->create();

        $response = $this->actingAs($user)->get(route('teams.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('teams/Index')
            ->has('teams', 2)
        );
    }
}
