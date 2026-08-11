<?php

namespace Tests\Unit\Services;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Exceptions\InvalidCurlCommandException;
use App\Services\CurlCommandParser;
use Tests\TestCase;

class CurlCommandParserTest extends TestCase
{
    private CurlCommandParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new CurlCommandParser;
    }

    public function test_it_reads_a_plain_get(): void
    {
        $parsed = $this->parser->parse('curl https://api.example.com/users');

        $this->assertSame('https://api.example.com/users', $parsed['url']);
        $this->assertSame(HttpMethod::Get, $parsed['method']);
        $this->assertSame(BodyType::None, $parsed['body_type']);
        $this->assertSame('users', $parsed['name']);
    }

    public function test_it_reads_headers_and_an_explicit_method(): void
    {
        $parsed = $this->parser->parse(
            'curl -X DELETE https://api.example.com/users/1 -H "Accept: application/json" -H \'X-Trace: abc\''
        );

        $this->assertSame(HttpMethod::Delete, $parsed['method']);
        $this->assertContains(['key' => 'Accept', 'value' => 'application/json', 'enabled' => true], $parsed['headers']);
        $this->assertContains(['key' => 'X-Trace', 'value' => 'abc', 'enabled' => true], $parsed['headers']);
    }

    public function test_it_follows_line_continuations(): void
    {
        $parsed = $this->parser->parse(<<<'CURL'
            curl https://api.example.com/users \
              -H 'Accept: application/json' \
              -X POST
            CURL);

        $this->assertSame('https://api.example.com/users', $parsed['url']);
        $this->assertSame(HttpMethod::Post, $parsed['method']);
        $this->assertCount(1, $parsed['headers']);
    }

    public function test_a_json_body_is_recognised_as_json(): void
    {
        $parsed = $this->parser->parse(
            'curl https://api.example.com/users -H "Content-Type: application/json" --data-raw \'{"name":"Ada","age":36}\''
        );

        $this->assertSame(BodyType::Json, $parsed['body_type']);
        $this->assertSame(['name' => 'Ada', 'age' => 36], $parsed['body']['json']);
        // curl turns itself into a POST the moment there's a body.
        $this->assertSame(HttpMethod::Post, $parsed['method']);
    }

    public function test_a_json_body_is_recognised_without_a_content_type(): void
    {
        $parsed = $this->parser->parse('curl https://api.example.com/users -d \'{"name":"Ada"}\'');

        $this->assertSame(BodyType::Json, $parsed['body_type']);
        $this->assertSame(['name' => 'Ada'], $parsed['body']['json']);
    }

    public function test_a_form_body_becomes_url_encoded_fields(): void
    {
        $parsed = $this->parser->parse(
            'curl https://api.example.com/login -d "email=ada@example.com&password=hunter2"'
        );

        $this->assertSame(BodyType::UrlEncoded, $parsed['body_type']);
        $this->assertSame([
            ['key' => 'email', 'value' => 'ada@example.com', 'enabled' => true],
            ['key' => 'password', 'value' => 'hunter2', 'enabled' => true],
        ], $parsed['body']['fields']);
    }

    public function test_multipart_fields_and_file_parts(): void
    {
        $parsed = $this->parser->parse(
            'curl https://api.example.com/avatars -F name=Ada -F "avatar=@/home/ada/face.png"'
        );

        $this->assertSame(BodyType::FormData, $parsed['body_type']);
        $this->assertSame(['key' => 'name', 'value' => 'Ada', 'enabled' => true], $parsed['body']['fields'][0]);

        $file = $parsed['body']['fields'][1];
        $this->assertSame('file', $file['type']);
        $this->assertSame('face.png', $file['filename']);
        $this->assertNull($file['file_id']);
    }

    public function test_basic_auth_from_the_user_flag(): void
    {
        $parsed = $this->parser->parse('curl -u ada:hunter2 https://api.example.com/me');

        $this->assertSame(AuthType::Basic, $parsed['auth_type']);
        $this->assertSame(['username' => 'ada', 'password' => 'hunter2'], $parsed['auth']);
    }

    public function test_a_bearer_header_becomes_structured_auth(): void
    {
        $parsed = $this->parser->parse(
            'curl https://api.example.com/me -H "Authorization: Bearer abc.def.ghi"'
        );

        $this->assertSame(AuthType::Bearer, $parsed['auth_type']);
        $this->assertSame(['token' => 'abc.def.ghi'], $parsed['auth']);
        // Lifted out of the headers so it doesn't also go out as a raw header.
        $this->assertSame([], $parsed['headers']);
    }

    public function test_an_unrecognised_authorization_header_stays_a_header(): void
    {
        $parsed = $this->parser->parse(
            'curl https://api.example.com/me -H "Authorization: Digest username=ada"'
        );

        $this->assertNull($parsed['auth_type']);
        $this->assertCount(1, $parsed['headers']);
    }

    public function test_query_params_are_split_out_of_the_url(): void
    {
        $parsed = $this->parser->parse('curl "https://api.example.com/users?page=2&sort=name"');

        $this->assertSame([
            ['key' => 'page', 'value' => '2', 'enabled' => true],
            ['key' => 'sort', 'value' => 'name', 'enabled' => true],
        ], $parsed['query_params']);
    }

    public function test_flags_we_do_not_model_are_ignored_rather_than_fatal(): void
    {
        $parsed = $this->parser->parse(
            'curl -sSL --compressed -k --max-time 30 --proxy http://proxy.local:8080 https://api.example.com/users'
        );

        // The proxy's URL must not be mistaken for the request's.
        $this->assertSame('https://api.example.com/users', $parsed['url']);
    }

    public function test_a_cookie_flag_becomes_a_cookie_header(): void
    {
        $parsed = $this->parser->parse('curl https://api.example.com/me -b "session=abc123"');

        $this->assertContains(['key' => 'Cookie', 'value' => 'session=abc123', 'enabled' => true], $parsed['headers']);
    }

    public function test_a_url_without_a_scheme_gets_one(): void
    {
        $parsed = $this->parser->parse('curl api.example.com/users');

        $this->assertSame('http://api.example.com/users', $parsed['url']);
    }

    public function test_ansi_c_quoting_from_a_browser_copy(): void
    {
        $parsed = $this->parser->parse("curl 'https://api.example.com/me' -H \$'X-Weird: value'");

        $this->assertContains(['key' => 'X-Weird', 'value' => 'value', 'enabled' => true], $parsed['headers']);
    }

    public function test_something_that_is_not_curl_is_rejected(): void
    {
        $this->expectException(InvalidCurlCommandException::class);

        $this->parser->parse('wget https://api.example.com/users');
    }

    public function test_a_curl_command_with_no_url_is_rejected(): void
    {
        $this->expectException(InvalidCurlCommandException::class);

        $this->parser->parse('curl -X POST -H "Accept: application/json"');
    }
}
