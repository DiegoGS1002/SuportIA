<?php

namespace App\Services\AI;

use App\AI\PromptTemplates\SupportPrompt;
use App\Models\ChatMessage;
use Illuminate\Support\Collection;

/**
 * Builds the final list of messages (system + history + user) sent to the
 * AI provider, based on the SupportPrompt template and the conversation
 * history stored in the database.
 */
final readonly class PromptManager
{
    public function __construct(
        private SupportPrompt $supportPrompt,
    ) {}

    /**
     * @param  Collection<int, ChatMessage>  $history
     * @param  array<int, array{title: string, content: string}>  $documents
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $history, ?string $software = null, array $documents = []): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->supportPrompt->build($software, $documents),
            ],
        ];

        foreach ($history as $message) {
            if (! in_array($message->role, [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT], true)) {
                continue;
            }

            $messages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $messages;
    }
}

