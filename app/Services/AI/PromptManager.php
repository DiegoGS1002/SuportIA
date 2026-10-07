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
    private const int RECENT_MESSAGES_LIMIT = 20;

    private const int SUMMARY_CHARACTERS_LIMIT = 8000;

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

        $eligibleHistory = $history
            ->filter(fn (ChatMessage $message): bool => in_array(
                $message->role,
                [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT],
                true,
            ));

        $olderMessages = $eligibleHistory->slice(0, -self::RECENT_MESSAGES_LIMIT);

        if ($olderMessages->isNotEmpty()) {
            $summary = $olderMessages
                ->map(fn (ChatMessage $message): string => strtoupper($message->role).': '.$message->content)
                ->implode("\n");

            $messages[] = [
                'role' => 'system',
                'content' => "Resumo do historico anterior:\n".mb_substr($summary, -self::SUMMARY_CHARACTERS_LIMIT),
            ];
        }

        foreach ($eligibleHistory->take(-self::RECENT_MESSAGES_LIMIT) as $message) {
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
