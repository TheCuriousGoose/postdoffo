<?php

namespace App\DTOs;

/**
 * Output of the pre-request phase: a fully-resolved outgoing request plus the
 * variable state at that point, so the post-response phase (running the test
 * script) can be re-entered later without redoing variable resolution or
 * re-running the pre-request script.
 */
final readonly class PreparedRequestData
{
    /**
     * @param  array<string, string>  $variables
     * @param  array<int, array{id: int, key: string, value: string}>  $environmentUpdates  Environment variable rows the pre-request script wrote via pm.environment.set(), already persisted by the time this is built.
     */
    public function __construct(
        public OutgoingRequestData $outgoing,
        public array $variables,
        public array $environmentUpdates = [],
    ) {}
}
