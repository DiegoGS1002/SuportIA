<?php

namespace App\Services\AI;

/**
 * Contract for any provider capable of generating chat completions
 * (with optional Function Calling / Tools support).
 */
interface AiServiceInterface
{
    /**
     * Send a conversation (list of messages) to the AI provider and get
     * back an assistant response, which may contain plain text and/or
     * one or more tool calls that the orchestrator must execute.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function complete(array $messages, array $tools = []): AiChatResponse;
}

