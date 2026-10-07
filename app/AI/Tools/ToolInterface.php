<?php

namespace App\AI\Tools;

/**
 * Contract for any Tool (Function Calling) that the SupportAgent can invoke.
 */
interface ToolInterface
{
    /**
     * The unique tool name, as exposed to the AI provider.
     */
    public function name(): string;

    /**
     * Human readable description of what the tool does, used by the model
     * to decide when to call it.
     */
    public function description(): string;

    /**
     * JSON Schema describing the tool parameters.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Executes the tool with the given (already decoded) arguments and
     * returns a normalized result for the orchestrator.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): mixed;
}
