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
     */
    public function __construct(
        public OutgoingRequestData $outgoing,
        public array $variables,
    ) {}
}
