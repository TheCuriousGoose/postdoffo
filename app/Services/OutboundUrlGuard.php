<?php

namespace App\Services;

use App\Exceptions\BlockedOutboundUrlException;

/**
 * Decides whether this server is willing to fire a request at a given URL.
 *
 * Every request a user types is fetched *by the server*, which makes the app an
 * authenticated HTTP proxy sitting inside our own network. Without a check here,
 * "https://api.example.com" and "http://169.254.169.254/latest/meta-data/" are
 * the same kind of request as far as the executor is concerned, and the second
 * one reads cloud credentials straight out of the instance metadata service.
 *
 * So: only http(s), and the hostname has to resolve to a public address. Private
 * and loopback ranges are exactly the ones a self-hosted install legitimately
 * wants, which is why this is a config toggle rather than a hard rule — and why
 * a hosted deployment leaves it on. Note the browser-side path (localRequest.ts)
 * is unaffected either way: .test/.local/localhost requests are fired by the
 * user's own browser, from their own machine, and never reach this guard.
 */
class OutboundUrlGuard
{
    /**
     * Ranges that are never a legitimate target for a proxied request. Guarded by
     * FILTER_FLAG_NO_PRIV_RANGE/NO_RES_RANGE plus the cases those two miss.
     *
     * @var array<int, string>
     */
    private const EXTRA_BLOCKED_CIDRS = [
        '100.64.0.0/10',    // carrier-grade NAT
        '192.0.0.0/24',     // IETF protocol assignments
        '198.18.0.0/15',    // benchmarking
        '::ffff:0:0/96',    // IPv4-mapped IPv6, e.g. ::ffff:127.0.0.1
        '64:ff9b::/96',     // NAT64, another route back to an IPv4 literal
        'fe80::/10',        // IPv6 link-local
    ];

    public function enabled(): bool
    {
        return (bool) config('requests.block_private_hosts');
    }

    /**
     * @throws BlockedOutboundUrlException
     */
    public function assertAllowed(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // Checked whether or not the private-host block is on: file:// and friends
        // read the server's own disk, which no deployment wants.
        if (! in_array($scheme, ['http', 'https'], strict: true)) {
            throw new BlockedOutboundUrlException(
                $scheme === ''
                    ? 'That URL is missing a scheme — use http:// or https://.'
                    : "Requests to {$scheme}:// URLs are not allowed."
            );
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new BlockedOutboundUrlException('That URL has no host to send to.');
        }

        if (! $this->enabled()) {
            return;
        }

        foreach ($this->resolve($host) as $ip) {
            if (! $this->isPublic($ip)) {
                throw new BlockedOutboundUrlException(
                    "This server won't send requests to private or internal addresses ({$host} resolves to {$ip}). "
                    .'Point the request at a host reachable from the internet, or use a hostname ending in '
                    .'.test/.local/.localhost — those are sent from your browser instead.'
                );
            }
        }
    }

    /**
     * Every address the hostname answers with, since a name resolving to one
     * public and one private address is a way to smuggle the private one past a
     * check that only looks at the first result.
     *
     * @return array<int, string>
     */
    private function resolve(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );

        if ($ips === []) {
            throw new BlockedOutboundUrlException("Could not resolve {$host}.");
        }

        return $ips;
    }

    private function isPublic(string $ip): bool
    {
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;

        if (! $isPublic) {
            return false;
        }

        foreach (self::EXTRA_BLOCKED_CIDRS as $cidr) {
            if ($this->inCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipBinary = inet_pton($ip);
        $subnetBinary = inet_pton($subnet);

        // Different families (v4 address against a v6 range) can't overlap.
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $bits;
        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if (substr($ipBinary, 0, $wholeBytes) !== substr($subnetBinary, 0, $wholeBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = chr(0xFF << (8 - $remainingBits) & 0xFF);

        return ($ipBinary[$wholeBytes] & $mask) === ($subnetBinary[$wholeBytes] & $mask);
    }
}
