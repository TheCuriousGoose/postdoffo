<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\RequestHistory;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceVariable;
use App\Services\VariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Values that can hold a credential must not be readable straight out of the
 * database — the threat here is a leaked dump or backup, not a workspace member,
 * who still sees plaintext through the app.
 */
class EncryptedSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_environment_variable_value_is_encrypted_in_the_database(): void
    {
        $workspace = Workspace::factory()->create(['owner_id' => User::factory()]);
        $environment = Environment::factory()->create(['workspace_id' => $workspace->id]);

        $variable = EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'token',
            'value' => 'super-secret-token',
            'is_secret' => true,
        ]);

        $stored = DB::table('environment_variables')->where('id', $variable->id)->value('value');

        $this->assertNotSame('super-secret-token', $stored);
        $this->assertStringNotContainsString('super-secret-token', (string) $stored);
        $this->assertSame('super-secret-token', $variable->fresh()->value);
    }

    public function test_a_workspace_global_value_is_encrypted_in_the_database(): void
    {
        $workspace = Workspace::factory()->create(['owner_id' => User::factory()]);

        $variable = WorkspaceVariable::factory()->create([
            'workspace_id' => $workspace->id,
            'key' => 'api_key',
            'value' => 'sk-live-abcdef',
        ]);

        $stored = DB::table('workspace_variables')->where('id', $variable->id)->value('value');

        $this->assertStringNotContainsString('sk-live-abcdef', (string) $stored);
        $this->assertSame('sk-live-abcdef', $variable->fresh()->value);
    }

    public function test_a_recorded_response_body_is_encrypted_in_the_database(): void
    {
        $workspace = Workspace::factory()->create(['owner_id' => User::factory()]);

        $history = RequestHistory::create([
            'workspace_id' => $workspace->id,
            'method' => 'POST',
            'url' => 'https://api.example.com/login',
            'status_code' => 200,
            'duration_ms' => 12,
            'response_snapshot' => ['body' => '{"access_token":"leaked-token-value"}'],
            'executed_at' => now(),
        ]);

        $stored = DB::table('request_history')->where('id', $history->id)->value('response_snapshot');

        $this->assertStringNotContainsString('leaked-token-value', (string) $stored);
        $this->assertSame(
            '{"access_token":"leaked-token-value"}',
            $history->fresh()->response_snapshot['body'],
        );
    }

    public function test_variables_still_resolve_through_the_app(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $environment = Environment::factory()->create(['workspace_id' => $workspace->id]);
        EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'base_url',
            'value' => 'https://api.example.com',
        ]);

        $resolved = app(VariableResolver::class)
            ->resolve(null, $environment->fresh(), [], $workspace);

        $this->assertSame('https://api.example.com', $resolved['base_url']);
    }
}
