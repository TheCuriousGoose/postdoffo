<?php

namespace App\DTOs;

final readonly class TestResultData
{
    public function __construct(
        public string $name,
        public bool $passed,
        public ?string $message = null,
    ) {}

    /**
     * @return array{name: string, passed: bool, message: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'passed' => $this->passed,
            'message' => $this->message,
        ];
    }
}
