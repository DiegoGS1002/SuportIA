<?php

namespace App\AI\Tools;

/**
 * Base implementation shared by all Tools, handling the Function Calling
 * schema payload expected by the OpenAI Chat Completions API.
 */
abstract class BaseTool implements ToolInterface
{
    /**
     * Converts the tool definition into the OpenAI "tools" payload format.
     *
     * @return array<string, mixed>
     */
    final public function toPayload(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->parameters(),
            ],
        ];
    }
}

