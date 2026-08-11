<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceVariable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceVariableTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_workspace_global(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.workspace-variables.store', $workspace), [
                'key' => 'base_url',
                'value' => 'https://api.example.com',
                'is_secret' => false,
            ])
            ->assertOk()
            ->assertJsonPath('key', 'base_url');

        $this->assertDatabaseHas('workspace_variables', [
            'workspace_id' => $workspace->id,
            'key' => 'base_url',
        ]);
        // Read back through the model: the column itself holds ciphertext now.
        $this->assertSame(
            'https://api.example.com',
            WorkspaceVariable::where('key', 'base_url')->value('value'),
        );
    }

    public function test_storing_the_same_key_updates_rather_than_duplicates(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        foreach (['first', 'second'] as $value) {
            $this->actingAs($user)
                ->postJson(route('api.workspace-variables.store', $workspace), [
                    'key' => 'token',
                    'value' => $value,
                ])
                ->assertOk();
        }

        $this->assertSame(1, WorkspaceVariable::where('workspace_id', $workspace->id)->count());
        $this->assertSame('second', WorkspaceVariable::where('key', 'token')->value('value'));
    }

    public function test_variable_can_be_updated_and_deleted(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $variable = WorkspaceVariable::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($user)
            ->patchJson(route('api.workspace-variables.update', $variable), ['is_secret' => true])
            ->assertOk();
        $this->assertTrue($variable->fresh()->is_secret);

        $this->actingAs($user)
            ->deleteJson(route('api.workspace-variables.destroy', $variable))
            ->assertNoContent();
        $this->assertDatabaseMissing('workspace_variables', ['id' => $variable->id]);
    }

    public function test_stranger_cannot_manage_another_workspaces_globals(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($stranger)
            ->postJson(route('api.workspace-variables.store', $workspace), ['key' => 'x'])
            ->assertForbidden();
    }
}
