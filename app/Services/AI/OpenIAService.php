<?php

namespace App\Services\AI;

use OpenAI\Client;

/**
 * OpenAI implementation of the AiServiceInterface, using the Chat
 * Completions API (with Function Calling / Tools support).
 */
final readonly class OpenIAService implements AiServiceInterface
{
    public function __construct(
        private Client $client,
        private string $model,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function complete(array $messages, array $tools = []): AiChatResponse
    {
        $parameters = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $parameters['tools'] = $tools;
            $parameters['tool_choice'] = 'auto';
        }

        $response = $this->client->chat()->create($parameters);

        $message = $response->choices[0]->message;

        $toolCalls = [];

        foreach ($message->toolCalls ?? [] as $toolCall) {
            $toolCalls[] = [
                'id' => $toolCall->id,
                'name' => $toolCall->function->name,
                'arguments' => json_decode($toolCall->function->arguments, true, flags: JSON_THROW_ON_ERROR) ?? [],
            ];
        }

        return new AiChatResponse(
            content: $message->content,
            toolCalls: $toolCalls,
        );
    }
}
