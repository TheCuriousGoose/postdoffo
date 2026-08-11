<?php

namespace App\Services;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Exceptions\InvalidCurlCommandException;

/**
 * Turns a pasted `curl` command into the fields of a request.
 *
 * The point is the copy-paste-from-somewhere-else workflow: docs, a browser's
 * "Copy as cURL", a colleague's message. That means being generous about what
 * arrives — line continuations, either quote style, long and short flag names,
 * flags we don't model — and never failing on an option we simply don't support,
 * since a request with one flag ignored is far more useful than an error.
 */
class CurlCommandParser
{
    /**
     * Flags that take a value we don't model (--cacert, --proxy, ...). Listed so
     * their value isn't mistaken for the URL.
     *
     * @var array<int, string>
     */
    private const IGNORED_VALUE_FLAGS = [
        '--cacert', '--cert', '--key', '--proxy', '--proxy-user', '--connect-timeout',
        '--max-time', '--retry', '--resolve', '--interface', '--limit-rate',
        '-m', '-x', '-A', '--user-agent', '-e', '--referer', '--output', '-o',
    ];

    /**
     * @return array<string, mixed> Attributes ready for Request::create()/update().
     *
     * @throws InvalidCurlCommandException
     */
    public function parse(string $command): array
    {
        $tokens = $this->tokenize($command);

        if ($tokens === [] || ! in_array('curl', [$tokens[0], basename($tokens[0])], strict: true)) {
            throw new InvalidCurlCommandException('That doesn\'t look like a curl command — it should start with "curl".');
        }

        array_shift($tokens);

        $url = null;
        $method = null;
        $headers = [];
        $dataParts = [];
        $formFields = [];
        $isUrlEncodedForm = false;
        $auth = null;
        $authType = null;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            switch (true) {
                case $token === '-X' || $token === '--request':
                    $method = strtoupper((string) ($tokens[++$i] ?? ''));
                    break;

                case $token === '-H' || $token === '--header':
                    [$name, $value] = $this->splitHeader((string) ($tokens[++$i] ?? ''));

                    if ($name !== null) {
                        $headers[$name] = $value;
                    }
                    break;

                case $token === '-d' || $token === '--data' || $token === '--data-raw' || $token === '--data-binary' || $token === '--data-ascii':
                    $dataParts[] = (string) ($tokens[++$i] ?? '');
                    break;

                case $token === '--data-urlencode':
                    $dataParts[] = (string) ($tokens[++$i] ?? '');
                    $isUrlEncodedForm = true;
                    break;

                case $token === '-F' || $token === '--form':
                    $formFields[] = (string) ($tokens[++$i] ?? '');
                    break;

                case $token === '-u' || $token === '--user':
                    [$username, $password] = array_pad(explode(':', (string) ($tokens[++$i] ?? ''), 2), 2, '');
                    $authType = AuthType::Basic;
                    $auth = ['username' => $username, 'password' => $password];
                    break;

                case $token === '-b' || $token === '--cookie':
                    $headers['Cookie'] = (string) ($tokens[++$i] ?? '');
                    break;

                case $token === '--url':
                    $url = (string) ($tokens[++$i] ?? '');
                    break;

                case in_array($token, self::IGNORED_VALUE_FLAGS, strict: true):
                    $i++;
                    break;

                    // Bare flags (-L, --compressed, -k, -s, -v, ...) carry no value
                    // and nothing we model, so they simply drop out.
                case str_starts_with($token, '-'):
                    break;

                default:
                    $url ??= $token;
            }
        }

        if ($url === null || $url === '') {
            throw new InvalidCurlCommandException('No URL found in that curl command.');
        }

        [$bodyType, $body] = $this->body($dataParts, $formFields, $isUrlEncodedForm, $headers);

        // An Authorization header is more specific than nothing, but -u is more
        // specific than a header, so it wins where both are present.
        if ($authType === null) {
            [$authType, $auth] = $this->authFromHeaders($headers);
        }

        return [
            'name' => $this->nameFrom($url),
            'url' => $this->normalizeUrl($url),
            'method' => $this->method($method, $bodyType),
            'headers' => $this->headerList($headers),
            'query_params' => $this->queryParams($url),
            'body' => $body,
            'body_type' => $bodyType,
            'auth_type' => $authType,
            'auth' => $auth,
        ];
    }

    /**
     * Split on whitespace, honouring single/double quotes, backslash and caret
     * line continuations, and ANSI-C $'...' quoting (Chrome's "Copy as cURL"
     * emits it for headers containing newlines).
     *
     * @return array<int, string>
     */
    private function tokenize(string $command): array
    {
        $command = preg_replace('/\\\\\r?\n|\^\r?\n|`\r?\n/', ' ', trim($command)) ?? '';

        $tokens = [];
        $current = '';
        $quote = null;
        $started = false;
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;

                    continue;
                }

                // Only a double-quoted context treats a backslash as an escape;
                // inside single quotes the shell passes it through verbatim.
                if ($char === '\\' && $quote === '"' && $i + 1 < $length) {
                    $current .= $command[++$i];

                    continue;
                }

                $current .= $char;

                continue;
            }

            if ($char === '"' || $char === "'") {
                // $'...' — strip the leading $ that ANSI-C quoting adds.
                if ($char === "'" && $current === '$') {
                    $current = '';
                }

                $quote = $char;
                $started = true;

                continue;
            }

            if (preg_match('/\s/', $char)) {
                if ($started || $current !== '') {
                    $tokens[] = $current;
                    $current = '';
                    $started = false;
                }

                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $command[++$i];

                continue;
            }

            $current .= $char;
        }

        if ($started || $current !== '') {
            $tokens[] = $current;
        }

        return $tokens;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function splitHeader(string $header): array
    {
        $colon = strpos($header, ':');

        if ($colon === false) {
            return [null, ''];
        }

        return [trim(substr($header, 0, $colon)), trim(substr($header, $colon + 1))];
    }

    /**
     * @param  array<int, string>  $dataParts
     * @param  array<int, string>  $formFields
     * @param  array<string, string>  $headers
     * @return array{0: BodyType, 1: array<string, mixed>|null}
     */
    private function body(array $dataParts, array $formFields, bool $isUrlEncodedForm, array &$headers): array
    {
        if ($formFields !== []) {
            return [BodyType::FormData, ['fields' => $this->formDataFields($formFields)]];
        }

        if ($dataParts === []) {
            return [BodyType::None, null];
        }

        $raw = implode('&', $dataParts);
        $contentType = strtolower($this->header($headers, 'content-type') ?? '');

        if (str_contains($contentType, 'json') || $this->looksLikeJson($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [BodyType::Json, ['json' => $decoded]];
            }
        }

        // curl defaults -d to form encoding, and that's also what an explicit
        // --data-urlencode or a matching Content-Type means.
        if ($isUrlEncodedForm || str_contains($contentType, 'x-www-form-urlencoded') || ($contentType === '' && $this->looksLikeQueryString($raw))) {
            return [BodyType::UrlEncoded, ['fields' => $this->urlEncodedFields($raw)]];
        }

        return [BodyType::Raw, ['raw' => $raw]];
    }

    /**
     * @param  array<int, string>  $formFields
     * @return array<int, array<string, mixed>>
     */
    private function formDataFields(array $formFields): array
    {
        $fields = [];

        foreach ($formFields as $field) {
            [$key, $value] = array_pad(explode('=', $field, 2), 2, '');

            // curl marks a file part with a leading @ or <; we can't reach the
            // uploader's disk, so it comes in as a file row waiting to be picked.
            if (str_starts_with($value, '@') || str_starts_with($value, '<')) {
                $fields[] = [
                    'key' => $key,
                    'value' => '',
                    'enabled' => true,
                    'type' => 'file',
                    'file_id' => null,
                    'filename' => basename(str_replace('\\', '/', substr($value, 1))) ?: null,
                ];

                continue;
            }

            $fields[] = ['key' => $key, 'value' => $value, 'enabled' => true];
        }

        return $fields;
    }

    /**
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function urlEncodedFields(string $raw): array
    {
        $fields = [];

        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');

            $fields[] = [
                'key' => urldecode($key),
                'value' => urldecode($value),
                'enabled' => true,
            ];
        }

        return $fields;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0: AuthType|null, 1: array<string, string>|null}
     */
    private function authFromHeaders(array &$headers): array
    {
        $authorization = $this->header($headers, 'authorization');

        if ($authorization === null) {
            return [null, null];
        }

        if (stripos($authorization, 'bearer ') === 0) {
            $this->forgetHeader($headers, 'authorization');

            return [AuthType::Bearer, ['token' => substr($authorization, 7)]];
        }

        if (stripos($authorization, 'basic ') === 0) {
            $decoded = base64_decode(substr($authorization, 6), true);

            if ($decoded !== false && str_contains($decoded, ':')) {
                $this->forgetHeader($headers, 'authorization');
                [$username, $password] = explode(':', $decoded, 2);

                return [AuthType::Basic, ['username' => $username, 'password' => $password]];
            }
        }

        // Anything else stays a plain header rather than being guessed at.
        return [null, null];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function forgetHeader(array &$headers, string $name): void
    {
        foreach (array_keys($headers) as $key) {
            if (strtolower($key) === $name) {
                unset($headers[$key]);
            }
        }
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function headerList(array $headers): array
    {
        $list = [];

        foreach ($headers as $key => $value) {
            $list[] = ['key' => $key, 'value' => $value, 'enabled' => true];
        }

        return $list;
    }

    /**
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function queryParams(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return [];
        }

        return $this->urlEncodedFields($query);
    }

    private function method(?string $method, BodyType $bodyType): HttpMethod
    {
        if ($method !== null && $method !== '') {
            return HttpMethod::tryFrom($method) ?? HttpMethod::Get;
        }

        // curl switches to POST on its own as soon as there's a body.
        return $bodyType === BodyType::None ? HttpMethod::Get : HttpMethod::Post;
    }

    /** curl assumes http:// when the URL has no scheme; so do we. */
    private function normalizeUrl(string $url): string
    {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : 'http://'.$url;
    }

    /** The last path segment makes a better tab label than the whole URL. */
    private function nameFrom(string $url): string
    {
        $path = trim((string) parse_url($this->normalizeUrl($url), PHP_URL_PATH), '/');

        if ($path === '') {
            return (string) (parse_url($this->normalizeUrl($url), PHP_URL_HOST) ?: 'Imported request');
        }

        $segments = explode('/', $path);

        return end($segments) ?: 'Imported request';
    }

    private function looksLikeJson(string $raw): bool
    {
        $trimmed = ltrim($raw);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private function looksLikeQueryString(string $raw): bool
    {
        return str_contains($raw, '=') && ! str_contains($raw, "\n");
    }
}
