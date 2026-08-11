<?php

namespace Tests\Feature;

use App\Actions\ExecuteRequestAction;
use App\Enums\HttpMethod;
use App\Models\Collection;
use App\Models\Request as RequestModel;
use App\Models\RequestCookie;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The cookie jar is what makes "log in, then call the authenticated endpoint"
 * work the way it does in a browser, which is most of what testing a
 * session-authenticated API consists of.
 */
class CookieJarTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_set_cookie_response_is_stored_for_that_user(): void
    {
        $user = User::factory()->create();
        $login = $this->requestFor($user, 'https://api.example.com/login', HttpMethod::Post);

        Http::fake([
            'api.example.com/*' => Http::response('', 200, [
                'Set-Cookie' => 'session=abc123; Path=/; HttpOnly',
            ]),
        ]);

        app(ExecuteRequestAction::class)->handle($login, $user);

        $cookie = RequestCookie::firstOrFail();

        $this->assertSame('session', $cookie->name);
        $this->assertSame('abc123', $cookie->value);
        $this->assertSame('api.example.com', $cookie->domain);
        $this->assertSame($user->id, $cookie->user_id);
        $this->assertTrue($cookie->http_only);
    }

    public function test_a_stored_cookie_is_sent_on_the_next_matching_request(): void
    {
        $user = User::factory()->create();
        $login = $this->requestFor($user, 'https://api.example.com/login', HttpMethod::Post);
        $me = RequestModel::factory()->create([
            'collection_id' => $login->collection_id,
            'url' => 'https://api.example.com/me',
        ]);

        Http::fake([
            'api.example.com/login' => Http::response('', 200, [
                'Set-Cookie' => 'session=abc123; Path=/',
            ]),
            'api.example.com/me' => Http::response(['name' => 'Ada'], 200),
        ]);

        app(ExecuteRequestAction::class)->handle($login, $user);
        app(ExecuteRequestAction::class)->handle($me, $user);

        Http::assertSent(fn ($sent) => str_contains($sent->url(), '/me')
            && $sent->hasHeader('Cookie', 'session=abc123'));
    }

    public function test_a_cookie_is_not_sent_to_a_different_domain(): void
    {
        $user = User::factory()->create();
        $login = $this->requestFor($user, 'https://api.example.com/login', HttpMethod::Post);
        $elsewhere = RequestModel::factory()->create([
            'collection_id' => $login->collection_id,
            'url' => 'https://other.example.org/me',
        ]);

        Http::fake([
            'api.example.com/*' => Http::response('', 200, ['Set-Cookie' => 'session=abc123; Path=/']),
            'other.example.org/*' => Http::response([], 200),
        ]);

        app(ExecuteRequestAction::class)->handle($login, $user);
        app(ExecuteRequestAction::class)->handle($elsewhere, $user);

        Http::assertSent(fn ($sent) => str_contains($sent->url(), 'other.example.org')
            && ! $sent->hasHeader('Cookie'));
    }

    public function test_one_members_session_does_not_leak_to_another(): void
    {
        $owner = User::factory()->create();
        $colleague = User::factory()->create();
        $login = $this->requestFor($owner, 'https://api.example.com/login', HttpMethod::Post);

        Http::fake(['api.example.com/*' => Http::response('', 200, [
            'Set-Cookie' => 'session=owner-session; Path=/',
        ])]);

        app(ExecuteRequestAction::class)->handle($login, $owner);

        $me = RequestModel::factory()->create([
            'collection_id' => $login->collection_id,
            'url' => 'https://api.example.com/me',
        ]);

        app(ExecuteRequestAction::class)->handle($me, $colleague);

        Http::assertSent(fn ($sent) => str_contains($sent->url(), '/me')
            && ! $sent->hasHeader('Cookie'));
    }

    public function test_a_cleared_cookie_is_removed_rather_than_kept(): void
    {
        $user = User::factory()->create();
        $login = $this->requestFor($user, 'https://api.example.com/login', HttpMethod::Post);
        $logout = RequestModel::factory()->create([
            'collection_id' => $login->collection_id,
            'url' => 'https://api.example.com/logout',
            'method' => HttpMethod::Post,
        ]);

        Http::fake([
            'api.example.com/login' => Http::response('', 200, ['Set-Cookie' => 'session=abc123; Path=/']),
            // How an API ends a session: same cookie, emptied and back-dated.
            'api.example.com/logout' => Http::response('', 200, [
                'Set-Cookie' => 'session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT',
            ]),
        ]);

        app(ExecuteRequestAction::class)->handle($login, $user);
        $this->assertSame(1, RequestCookie::count());

        app(ExecuteRequestAction::class)->handle($logout, $user);
        $this->assertSame(0, RequestCookie::count());
    }

    public function test_the_cookie_value_is_encrypted_in_the_database(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $cookie = RequestCookie::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'value' => 'a-real-session-id',
        ]);

        $stored = DB::table('request_cookies')->where('id', $cookie->id)->value('value');

        $this->assertStringNotContainsString('a-real-session-id', (string) $stored);
    }

    private function requestFor(User $user, string $url, HttpMethod $method = HttpMethod::Get): RequestModel
    {
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        return RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => $url,
            'method' => $method,
        ]);
    }
}
