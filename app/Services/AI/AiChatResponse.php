<?php

namespace App\Services\AI;

/**
 * Value object representing a normalized AI chat completion response.
 */
final readonly class AiChatResponse
{
    /**
     * @param  array<int, array{id: string, name: string, arguments: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public ?string $content,
        public array $toolCalls = [],
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
