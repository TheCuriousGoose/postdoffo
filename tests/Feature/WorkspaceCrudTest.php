<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('workspaces.show', $workspace))
            ->assertOk();
    }

    public function test_creating_a_workspace_through_the_endpoint_sets_the_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('workspaces.store'), ['name' => 'My Workspace'])
            ->assertRedirect();

        $workspace = Workspace::where('name', 'My Workspace')->firstOrFail();
        $this->assertSame($user->id, $workspace->owner_id);
        $this->assertSame(WorkspaceRole::Owner, $workspace->roleFor($user));
    }

    public function test_stranger_cannot_view_a_workspace_they_do_not_belong_to(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($stranger)
            ->get(route('workspaces.show', $workspace))
            ->assertForbidden();
    }

    public function test_editor_member_can_create_a_collection_but_viewer_cannot(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);
        $workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->actingAs($editor)
            ->postJson(route('api.collections.store', $workspace), ['name' => 'Editor Collection'])
            ->assertOk()
            ->assertJsonPath('name', 'Editor Collection');

        $this->actingAs($viewer)
            ->postJson(route('api.collections.store', $workspace), ['name' => 'Viewer Collection'])
            ->assertForbidden();

        $this->assertSame(1, Collection::count());
    }

    public function test_a_request_can_be_created_updated_and_deleted_within_a_collection(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $created = $this->actingAs($user)
            ->postJson(route('api.requests.store', $collection), [
                'name' => 'Get Ping',
                'method' => 'GET',
                'url' => 'https://api.example.com/ping',
            ])
            ->assertOk()
            ->json();

        $this->actingAs($user)
            ->patchJson(route('api.requests.update', $created['id']), ['name' => 'Ping Renamed'])
            ->assertOk()
            ->assertJsonPath('name', 'Ping Renamed');

        $this->actingAs($user)
            ->deleteJson(route('api.requests.destroy', $created['id']))
            ->assertNoContent();

        $this->assertSame(0, Request::count());
    }

    public function test_a_request_can_be_created_with_a_blank_url(): void
    {
        // This is exactly what the "New request" button in CollectionTree.vue
        // sends — the url is filled in afterwards in the editor.
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($user)
            ->postJson(route('api.requests.store', $collection), [
                'name' => 'New Request',
                'method' => 'GET',
                'url' => '',
            ])
            ->assertOk()
            ->assertJsonPath('url', '');
    }

    public function test_activating_an_environment_deactivates_the_previous_one(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $envA = Environment::factory()->create(['workspace_id' => $workspace->id, 'is_active' => true]);
        $envB = Environment::factory()->create(['workspace_id' => $workspace->id, 'is_active' => false]);

        $this->actingAs($user)
            ->postJson(route('api.environments.activate', $envB))
            ->assertOk();

        $this->assertFalse($envA->fresh()->is_active);
        $this->assertTrue($envB->fresh()->is_active);
    }

    public function test_reactivating_an_already_active_environment_stays_active(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $env = Environment::factory()->create(['workspace_id' => $workspace->id, 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('api.environments.activate', $env))
            ->assertOk();

        $this->assertTrue($env->fresh()->is_active);
    }
}
