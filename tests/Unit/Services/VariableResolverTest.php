<?php

namespace Tests\Unit\Services;

use App\Enums\AuthType;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Request;
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

    public function test_oauth1_produces_a_verifiable_hmac_sha1_signature(): void
    {
        $resolver = new VariableResolver;

        $request = Request::factory()->make([
            'auth_type' => AuthType::OAuth1,
            'auth' => [
                'consumerKey' => 'consumer-key',
                'consumerSecret' => 'consumer-secret',
                'accessToken' => 'access-token',
                'tokenSecret' => 'token-secret',
                'signatureMethod' => 'HMAC-SHA1',
            ],
        ]);

        $auth = $resolver->resolveAuth($request, [], 'GET', 'https://api.example.com/resource?foo=bar');

        $this->assertNotNull($auth);
        $this->assertSame('header', $auth['location']);
        $this->assertSame('Authorization', $auth['key']);
        $this->assertStringStartsWith('OAuth ', $auth['value']);
        $this->assertMatchesRegularExpression('/oauth_consumer_key="consumer-key"/', $auth['value']);
        $this->assertMatchesRegularExpression('/oauth_token="access-token"/', $auth['value']);
        $this->assertMatchesRegularExpression('/oauth_signature_method="HMAC-SHA1"/', $auth['value']);

        preg_match('/oauth_signature="([^"]+)"/', $auth['value'], $sigMatch);
        preg_match('/oauth_nonce="([^"]+)"/', $auth['value'], $nonceMatch);
        preg_match('/oauth_timestamp="([^"]+)"/', $auth['value'], $timestampMatch);

        $params = [
            'foo' => 'bar',
            'oauth_consumer_key' => 'consumer-key',
            'oauth_token' => 'access-token',
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $timestampMatch[1],
            'oauth_nonce' => $nonceMatch[1],
            'oauth_version' => '1.0',
        ];

        $pairs = [];

        foreach ($params as $key => $value) {
            $pairs[] = rawurlencode($key).'='.rawurlencode($value);
        }

        sort($pairs);

        $baseString = 'GET&'.rawurlencode('https://api.example.com/resource').'&'.rawurlencode(implode('&', $pairs));
        $signingKey = rawurlencode('consumer-secret').'&'.rawurlencode('token-secret');
        $expected = base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));

        $this->assertSame($expected, urldecode($sigMatch[1]));
    }

    public function test_oauth1_plaintext_uses_the_signing_key_directly(): void
    {
        $resolver = new VariableResolver;

        $request = Request::factory()->make([
            'auth_type' => AuthType::OAuth1,
            'auth' => [
                'consumerKey' => 'consumer-key',
                'consumerSecret' => 'consumer-secret',
                'accessToken' => '',
                'tokenSecret' => '',
                'signatureMethod' => 'PLAINTEXT',
            ],
        ]);

        $auth = $resolver->resolveAuth($request, [], 'POST', 'https://api.example.com/resource');

        preg_match('/oauth_signature="([^"]+)"/', $auth['value'], $sigMatch);

        $this->assertSame(rawurlencode('consumer-secret').'%26', $sigMatch[1]);
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
