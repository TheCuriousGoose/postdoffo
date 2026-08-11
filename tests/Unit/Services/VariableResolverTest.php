<?php

namespace Tests\Unit\Services;

use App\Models\Collection;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Workspace;
use App\Models\WorkspaceVariable;
use App\Services\VariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariableResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_interpolates_a_simple_template(): void
    {
        $resolver = new VariableResolver;

        $result = $resolver->interpolate('{{base_url}}/users/{{id}}', [
            'base_url' => 'https://api.example.com',
            'id' => '42',
        ]);

        $this->assertSame('https://api.example.com/users/42', $result);
    }

    public function test_it_leaves_unresolved_variables_untouched(): void
    {
        $resolver = new VariableResolver;

        $result = $resolver->interpolate('{{missing}}/ping', []);

        $this->assertSame('{{missing}}/ping', $result);
    }

    public function test_environment_overrides_collection_variables(): void
    {
        $resolver = new VariableResolver;

        $collection = Collection::factory()->create([
            'variables' => ['base_url' => 'https://collection.example.com'],
        ]);

        $environment = Environment::factory()->create(['workspace_id' => $collection->workspace_id]);
        EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'base_url',
            'value' => 'https://env.example.com',
        ]);

        $variables = $resolver->resolve($collection, $environment);

        $this->assertSame('https://env.example.com', $variables['base_url']);
    }

    public function test_nearer_collection_overrides_ancestor_collection(): void
    {
        $resolver = new VariableResolver;

        $root = Collection::factory()->create([
            'variables' => ['base_url' => 'https://root.example.com', 'shared' => 'yes'],
        ]);

        $child = Collection::factory()->create([
            'workspace_id' => $root->workspace_id,
            'parent_id' => $root->id,
            'variables' => ['base_url' => 'https://child.example.com'],
        ]);

        $variables = $resolver->resolve($child, null);

        $this->assertSame('https://child.example.com', $variables['base_url']);
        $this->assertSame('yes', $variables['shared']);
    }

    public function test_runtime_overrides_win_over_everything(): void
    {
        $resolver = new VariableResolver;

        $collection = Collection::factory()->create([
            'variables' => ['token' => 'collection-token'],
        ]);

        $environment = Environment::factory()->create(['workspace_id' => $collection->workspace_id]);
        EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'token',
            'value' => 'env-token',
        ]);

        $variables = $resolver->resolve($collection, $environment, ['token' => 'runtime-token']);

        $this->assertSame('runtime-token', $variables['token']);
    }

    public function test_workspace_globals_are_the_base_layer(): void
    {
        $resolver = new VariableResolver;

        $workspace = Workspace::factory()->create();
        WorkspaceVariable::factory()->create([
            'workspace_id' => $workspace->id,
            'key' => 'base_url',
            'value' => 'https://global.example.com',
        ]);
        WorkspaceVariable::factory()->create([
            'workspace_id' => $workspace->id,
            'key' => 'api_key',
            'value' => 'global-key',
        ]);

        $collection = Collection::factory()->create([
            'workspace_id' => $workspace->id,
            'variables' => ['base_url' => 'https://collection.example.com'],
        ]);

        $variables = $resolver->resolve($collection, null, [], $workspace);

        // Collection overrides the workspace global of the same name...
        $this->assertSame('https://collection.example.com', $variables['base_url']);
        // ...but the global with no override still comes through.
        $this->assertSame('global-key', $variables['api_key']);
    }

    public function test_environment_overrides_workspace_globals(): void
    {
        $resolver = new VariableResolver;

        $workspace = Workspace::factory()->create();
        WorkspaceVariable::factory()->create([
            'workspace_id' => $workspace->id,
            'key' => 'token',
            'value' => 'global-token',
        ]);

        $environment = Environment::factory()->create(['workspace_id' => $workspace->id]);
        EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'token',
            'value' => 'env-token',
        ]);

        $variables = $resolver->resolve(null, $environment, [], $workspace);

        $this->assertSame('env-token', $variables['token']);
    }

    public function test_interpolate_array_walks_nested_structures(): void
    {
        $resolver = new VariableResolver;

        $result = $resolver->interpolateArray([
            'headers' => ['Authorization' => 'Bearer {{token}}'],
            'nested' => ['deep' => ['value' => '{{token}}']],
        ], ['token' => 'abc123']);

        $this->assertSame('Bearer abc123', $result['headers']['Authorization']);
        $this->assertSame('abc123', $result['nested']['deep']['value']);
    }
}
