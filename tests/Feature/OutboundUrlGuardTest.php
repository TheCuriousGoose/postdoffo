<?php

namespace Tests\Feature;

use App\Actions\ExecuteRequestAction;
use App\Models\Collection;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The guard as the executor actually applies it — a refused target has to fail
 * as a readable error on the response rather than as a stack trace, and must not
 * reach the network at all.
 *
 * Targets here are written as literal IPs on purpose: a hostname would send the
 * guard off to do real DNS, which is neither reliable nor this test's business.
 */
class OutboundUrlGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite as a whole runs with the block off (see phpunit.xml).
        config(['requests.block_private_hosts' => true]);
    }

    public function test_a_request_at_an_internal_address_is_refused_before_it_is_sent(): void
    {
        $user = User::factory()->create();
        $request = $this->requestFor($user, 'http://169.254.169.254/latest/meta-data/iam/security-credentials/');

        Http::preventStrayRequests();

        $result = app(ExecuteRequestAction::class)->handle($request, $user);

        $this->assertNull($result->status);
        $this->assertStringContainsString('private or internal addresses', (string) $result->error);
    }

    public function test_a_redirect_into_an_internal_address_is_refused_mid_chain(): void
    {
        $user = User::factory()->create();
        $request = $this->requestFor($user, 'https://93.184.216.34/redirect-me');

        // The first hop is a perfectly ordinary public address; the target only
        // shows its hand in the Location header, which is exactly the bypass the
        // on_redirect check exists for.
        Http::fake([
            '93.184.216.34/*' => Http::response('', 302, [
                'Location' => 'http://169.254.169.254/latest/meta-data/',
            ]),
            '169.254.169.254/*' => Http::response('AWS credentials', 200),
        ]);

        $result = app(ExecuteRequestAction::class)->handle($request, $user);

        $this->assertNotSame(200, $result->status);
        $this->assertStringNotContainsString('AWS credentials', (string) $result->body);
        Http::assertNotSent(fn ($sent) => str_contains($sent->url(), '169.254.169.254'));
    }

    public function test_a_public_target_still_goes_through(): void
    {
        $user = User::factory()->create();
        $request = $this->requestFor($user, 'https://93.184.216.34/users');

        Http::fake(['93.184.216.34/*' => Http::response(['ok' => true], 200)]);

        $result = app(ExecuteRequestAction::class)->handle($request, $user);

        $this->assertSame(200, $result->status);
    }

    public function test_a_self_hosted_install_can_opt_back_into_internal_targets(): void
    {
        config(['requests.block_private_hosts' => false]);

        $user = User::factory()->create();
        $request = $this->requestFor($user, 'http://192.168.1.50/health');

        Http::fake(['192.168.1.50/*' => Http::response(['ok' => true], 200)]);

        $result = app(ExecuteRequestAction::class)->handle($request, $user);

        $this->assertSame(200, $result->status);
    }

    private function requestFor(User $user, string $url): RequestModel
    {
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        return RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => $url,
        ]);
    }
}
