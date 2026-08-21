<?php

namespace App\Services;

use App\Models\RequestCookie;
use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Carbon;

/**
 * Loads stored cookies into a Guzzle jar before a request and writes whatever
 * came back out of it afterwards, which is what makes a login request followed
 * by an authenticated one behave the way it does in a browser.
 *
 * Guzzle's own CookieJar already implements domain/path/secure matching and the
 * Set-Cookie parsing rules, so this deliberately doesn't reimplement any of
 * that — it only moves cookies between that jar and our table.
 */
class CookieJarService
{
    /**
     * A jar holding every unexpired cookie this user has for this workspace.
     * Guzzle narrows it down to the ones that actually match the request.
     */
    public function jarFor(string $workspaceId, ?User $user): CookieJar
    {
        $jar = new CookieJar;

        if (! $user) {
            return $jar;
        }

        $cookies = RequestCookie::forJar($workspaceId, $user->id)->unexpired()->get();

        foreach ($cookies as $cookie) {
            $jar->setCookie(new SetCookie([
                'Name' => $cookie->name,
                'Value' => $cookie->value,
                'Domain' => $cookie->domain,
                'Path' => $cookie->path,
                'Expires' => $cookie->expires_at?->getTimestamp(),
                'Secure' => $cookie->secure,
                'HttpOnly' => $cookie->http_only,
            ]));
        }

        return $jar;
    }

    /**
     * Write the jar back after a response. Cookies the server cleared (an empty
     * value, or an expiry in the past — how a logout deletes a session) are
     * removed rather than stored, so the jar doesn't keep resurrecting a session
     * the API has already ended.
     */
    public function persist(CookieJar $jar, string $workspaceId, ?User $user): void
    {
        if (! $user) {
            return;
        }

        foreach ($jar->toArray() as $cookie) {
            $domain = ltrim((string) ($cookie['Domain'] ?? ''), '.');
            $name = (string) ($cookie['Name'] ?? '');

            if ($domain === '' || $name === '') {
                continue;
            }

            $identity = [
                'workspace_id' => $workspaceId,
                'user_id' => $user->id,
                'domain' => $domain,
                'path' => (string) ($cookie['Path'] ?? '/'),
                'name' => $name,
            ];

            $expires = $cookie['Expires'] ?? null;
            $expiresAt = $expires ? Carbon::createFromTimestamp($expires) : null;
            $value = (string) ($cookie['Value'] ?? '');

            if ($value === '' || ($expiresAt && $expiresAt->isPast())) {
                RequestCookie::where($identity)->delete();

                continue;
            }

            RequestCookie::updateOrCreate($identity, [
                'value' => $value,
                'expires_at' => $expiresAt,
                'secure' => (bool) ($cookie['Secure'] ?? false),
                'http_only' => (bool) ($cookie['HttpOnly'] ?? false),
            ]);
        }
    }

    /**
     * The cookies a request would send, for display. Built by asking Guzzle to
     * dress a request for that URL rather than by matching domains here, so what
     * the user is shown is what would actually go out.
     *
     * @return array<int, array{name: string, value: string, domain: string, path: string}>
     */
    public function matching(string $workspaceId, ?User $user, string $url): array
    {
        $jar = $this->jarFor($workspaceId, $user);
        $matched = [];

        foreach ($jar->toArray() as $cookie) {
            $domain = ltrim((string) ($cookie['Domain'] ?? ''), '.');
            $host = (string) parse_url($url, PHP_URL_HOST);

            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                $matched[] = [
                    'name' => (string) $cookie['Name'],
                    'value' => (string) $cookie['Value'],
                    'domain' => $domain,
                    'path' => (string) ($cookie['Path'] ?? '/'),
                ];
            }
        }

        return $matched;
    }
}
