<?php

namespace App\Services;

use App\DTOs\OutgoingRequestData;
use App\Enums\BodyType;
use App\Models\RequestFile;

/**
 * Renders a resolved request as a curl command.
 *
 * It runs on an OutgoingRequestData — the same fully-resolved payload the
 * executor sends — so `{{variables}}`, collection headers and computed auth are
 * already baked in. That's the whole point: a snippet you can paste into a
 * terminal and have it do what the Send button just did, rather than one full of
 * placeholders that mean nothing outside this app.
 */
class CurlCommandGenerator
{
    public function generate(OutgoingRequestData $data): string
    {
        $parts = ['curl'];

        if ($data->method->value !== 'GET') {
            $parts[] = '-X '.$data->method->value;
        }

        $parts[] = $this->quote($this->url($data));

        foreach ($data->headers as $name => $value) {
            // The multipart boundary is curl's to choose, exactly as it is
            // Guzzle's when we send it ourselves.
            if ($data->bodyType === BodyType::FormData && strtolower($name) === 'content-type') {
                continue;
            }

            $parts[] = '-H '.$this->quote($name.': '.$value);
        }

        foreach ($this->bodyParts($data) as $part) {
            $parts[] = $part;
        }

        return implode(" \\\n  ", $parts);
    }

    private function url(OutgoingRequestData $data): string
    {
        if ($data->queryParams === []) {
            return $data->url;
        }

        // Query params are carried separately from the URL, so they have to be
        // folded back in — after whatever the URL already has.
        $separator = str_contains($data->url, '?') ? '&' : '?';

        return $data->url.$separator.http_build_query($data->queryParams);
    }

    /**
     * @return array<int, string>
     */
    private function bodyParts(OutgoingRequestData $data): array
    {
        $body = is_array($data->body) ? $data->body : [];

        return match ($data->bodyType) {
            BodyType::None => [],
            BodyType::Raw => $this->dataPart((string) ($body['raw'] ?? '')),
            BodyType::Json => $this->jsonParts($body['json'] ?? []),
            BodyType::UrlEncoded => $this->urlEncodedParts($body['fields'] ?? []),
            BodyType::FormData => $this->formDataParts($body['fields'] ?? []),
        };
    }

    /**
     * @return array<int, string>
     */
    private function dataPart(string $raw): array
    {
        return $raw === '' ? [] : ['--data-raw '.$this->quote($raw)];
    }

    /**
     * @return array<int, string>
     */
    private function jsonParts(mixed $json): array
    {
        $encoded = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? [] : ['--data-raw '.$this->quote($encoded)];
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, string>
     */
    private function urlEncodedParts(array $fields): array
    {
        $parts = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ($field['enabled'] ?? true) === false || ($field['key'] ?? '') === '') {
                continue;
            }

            $parts[] = '--data-urlencode '.$this->quote($field['key'].'='.($field['value'] ?? ''));
        }

        return $parts;
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, string>
     */
    private function formDataParts(array $fields): array
    {
        $parts = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ($field['enabled'] ?? true) === false || ($field['key'] ?? '') === '') {
                continue;
            }

            if (($field['type'] ?? 'text') !== 'file') {
                $parts[] = '-F '.$this->quote($field['key'].'='.($field['value'] ?? ''));

                continue;
            }

            // The upload lives on this server, so the snippet can only name the
            // file and leave the reader to point curl at their own copy.
            $filename = $this->filenameFor($field['file_id'] ?? null) ?? 'file';
            $parts[] = '-F '.$this->quote($field['key'].'=@/path/to/'.$filename);
        }

        return $parts;
    }

    private function filenameFor(mixed $fileId): ?string
    {
        return is_int($fileId) || is_string($fileId)
            ? RequestFile::whereKey($fileId)->value('filename')
            : null;
    }

    /**
     * Single quotes, POSIX style: everything inside is literal, so the only
     * thing needing care is a single quote itself, which has to be closed,
     * escaped and reopened.
     */
    private function quote(string $value): string
    {
        return "'".str_replace("'", "'\\''", $value)."'";
    }
}
