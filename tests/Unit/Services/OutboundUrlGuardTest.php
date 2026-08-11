<?php

namespace Tests\Unit\Services;

use App\Exceptions\BlockedOutboundUrlException;
use App\Services\OutboundUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OutboundUrlGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function blockedTargets(): array
    {
        return [
            'ipv4 loopback' => ['http://127.0.0.1/'],
            'ipv4 loopback, alternate notation' => ['http://127.1/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'ipv4-mapped ipv6 loopback' => ['http://[::ffff:127.0.0.1]/'],
            'link-local metadata service' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 link-local' => ['http://[fe80::1]/'],
            'rfc1918 class a' => ['http://10.0.0.5:8080/admin'],
            'rfc1918 class b' => ['http://172.16.4.4/'],
            'rfc1918 class c' => ['http://192.168.1.1/'],
            'carrier-grade nat' => ['http://100.64.0.1/'],
            'unique local ipv6' => ['http://[fd00::1]/'],
            'this-network' => ['http://0.0.0.0/'],
        ];
    }

    #[DataProvider('blockedTargets')]
    public function test_it_refuses_private_and_internal_addresses(string $url): void
    {
        $this->expectException(BlockedOutboundUrlException::class);

        $this->guard()->assertAllowed($url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedSchemes(): array
    {
        return [
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://example.com/secrets'],
            'gopher' => ['gopher://example.com:70/'],
            'no scheme' => ['example.com/users'],
        ];
    }

    #[DataProvider('blockedSchemes')]
    public function test_it_refuses_anything_that_is_not_http(string $url): void
    {
        $this->expectException(BlockedOutboundUrlException::class);

        $this->guard()->assertAllowed($url);
    }

    public function test_a_non_http_scheme_is_refused_even_with_the_private_block_off(): void
    {
        $this->expectException(BlockedOutboundUrlException::class);

        $this->guard(blockPrivate: false)->assertAllowed('file:///etc/passwd');
    }

    public function test_it_allows_a_public_address(): void
    {
        $this->guard()->assertAllowed('https://93.184.216.34/some/path');

        $this->expectNotToPerformAssertions();
    }

    public function test_private_addresses_are_allowed_when_the_block_is_turned_off(): void
    {
        $this->guard(blockPrivate: false)->assertAllowed('http://192.168.1.1/');

        $this->expectNotToPerformAssertions();
    }

    private function guard(bool $blockPrivate = true): OutboundUrlGuard
    {
        config(['requests.block_private_hosts' => $blockPrivate]);

        return new OutboundUrlGuard;
    }
}
