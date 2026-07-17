<?php

namespace App\DTOs;

use App\Enums\BodyType;
use App\Enums\HttpMethod;

/**
 * Fully-resolved request payload, ready to be fired by RequestExecutorService.
 * All {{variable}} interpolation must already have happened by the time this is built.
 */
final readonly class OutgoingRequestData
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $queryParams
     */
    public function __construct(
        public HttpMethod $method,
        public string $url,
        public array $headers,
        public array $queryParams,
        public mixed $body,
        public BodyType $bodyType,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            method: $data['method'] instanceof HttpMethod ? $data['method'] : HttpMethod::from($data['method']),
            url: $data['url'],
            headers: $data['headers'] ?? [],
            queryParams: $data['query_params'] ?? [],
            body: $data['body'] ?? null,
            bodyType: $data['body_type'] instanceof BodyType ? $data['body_type'] : BodyType::from($data['body_type'] ?? 'none'),
        );
    }
}
