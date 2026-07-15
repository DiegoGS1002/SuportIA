<?php

namespace App\AI\Agents;

use App\AI\Tools\WebSearchTool;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Services\AI\AiServiceInterface;
use App\Services\AI\PromptManager;
use App\Services\Search\VectorSearchService;
use App\Services\Vision\OCRService;
use Illuminate\Http\UploadedFile;

/**
 * SupportAgent - the orchestrator ("The Brain") of the agent loop.
 *
 * Responsible for:
 *  - Persisting the conversation history.
 *  - Running OCR when the user sends an image (print/screenshot).
 *  - Running the RAG search (Level 1 - internal knowledge base).
 *  - Deciding whether to call the WebSearchTool (Level 2 - external
 *    search), which only happens with explicit user consent.
 *  - Calling the AI provider to produce the final assistant answer.
 *
 * Mirrors the mandatory resolution flow described in prompt/AgenteIA.md.
 */
final readonly class SupportAgent
{
    private const string NOT_FOUND_MESSAGE = 'Nao foi possivel encontrar este erro em nosso banco de dados de solucoes. '.
        'Deseja que eu faca uma pesquisa externa na internet/OpenAI para tentar encontrar uma solucao para voce?';

    private const string DECLINED_MESSAGE = 'Entendido. Como nao encontramos a solucao internamente e a busca externa foi '.
        'recusada, estou finalizando este atendimento. Se precisar de algo mais, estou a disposicao!';

    public function __construct(
        private AiServiceInterface $aiService,
        private VectorSearchService $vectorSearchService,
        private PromptManager $promptManager,
        private OCRService $ocrService,
        private WebSearchTool $webSearchTool,
    ) {}

    /**
     * Handles a single turn of the conversation and returns the
     * persisted assistant ChatMessage.
     */
    public function handle(
        Chat $chat,
        ?string $userMessage,
        ?UploadedFile $image = null,
        bool $externalSearchConsent = false,
        bool $externalSearchDeclined = false,
    ): ChatMessage {
        $effectiveMessage = trim((string) $userMessage);

        if ($image !== null) {
            $extracted = $this->ocrService->extractText($image);

            $effectiveMessage = trim($effectiveMessage."\n\n[Texto extraido do print]\n".$extracted);
        }

        $this->storeUserMessage($chat, $effectiveMessage, $image);

        // Explicit refusal to proceed with the external (Level 2) search.
        if ($externalSearchDeclined) {
            return $this->storeAssistantMessage($chat, self::DECLINED_MESSAGE);
        }

        $documents = $this->vectorSearchService->search($effectiveMessage, $chat->software);

        if ($documents === [] && ! $externalSearchConsent) {
            return $this->storeAssistantMessage($chat, self::NOT_FOUND_MESSAGE, [
                'stage' => 'awaiting_external_search_consent',
            ]);
        }

        $messages = $this->promptManager->build(
            history: $chat->messages()->get(),
            software: $chat->software,
            documents: array_map(
                fn (array $document) => ['title' => $document['title'], 'content' => $document['content']],
                $documents,
            ),
        );

        $tools = $externalSearchConsent && $documents === []
            ? [$this->webSearchTool->toPayload()]
            : [];

        $response = $this->aiService->complete($messages, $tools);

        if ($response->hasToolCalls()) {
            foreach ($response->toolCalls as $toolCall) {
                if ($toolCall['name'] === $this->webSearchTool->name()) {
                    $toolResult = $this->webSearchTool->handle($toolCall['arguments']);

                    return $this->storeAssistantMessage($chat, $toolResult, [
                        'source' => 'external_search',
                    ]);
                }
            }
        }

        return $this->storeAssistantMessage(
            $chat,
            $response->content ?? 'Nao foi possivel gerar uma resposta no momento.',
            [
                'source' => $documents !== [] ? 'internal_knowledge_base' : 'model',
                'matched_documents' => array_column($documents, 'id'),
            ],
        );
    }

    private function storeUserMessage(Chat $chat, string $content, ?UploadedFile $image): ChatMessage
    {
        return $chat->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => $content,
            'metadata' => $image !== null ? ['has_image' => true] : null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function storeAssistantMessage(Chat $chat, string $content, ?array $metadata = null): ChatMessage
    {
        return $chat->messages()->create([
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $content,
            'metadata' => $metadata,
        ]);
    }
}

