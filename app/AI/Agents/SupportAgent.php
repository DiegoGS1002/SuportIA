<?php

namespace App\AI\Agents;

use App\AI\Tools\WebSearchTool;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Document;
use App\Services\AI\AiServiceInterface;
use App\Services\AI\PromptManager;
use App\Services\Search\VectorSearchService;
use App\Services\Vision\OCRService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

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

    private const string CONSENT_MESSAGE = 'Sim, autorizo a pesquisa externa.';

    private const string DECLINE_MESSAGE = 'Nao autorizo a pesquisa externa.';

    private const string IMAGE_WITHOUT_TEXT_MESSAGE = 'Nao consegui identificar texto legivel na imagem. '.
        'Descreva a mensagem de erro ou o comportamento apresentado para que eu possa continuar.';

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
        ?string $idempotencyKey = null,
    ): ChatMessage {
        if ($externalSearchConsent || $externalSearchDeclined) {
            return $this->handleExternalSearchDecision(
                chat: $chat,
                consented: $externalSearchConsent,
                idempotencyKey: $idempotencyKey,
            );
        }

        $effectiveMessage = trim((string) $userMessage);

        if ($image !== null) {
            $extracted = $this->ocrService->extractText($image);

            if ($extracted !== '') {
                $effectiveMessage = trim($effectiveMessage."\n\n[Texto extraido do print]\n".$extracted);
            }
        }

        if ($effectiveMessage === '') {
            $storedUserMessage = $this->storeUserMessage(
                $chat,
                '[Imagem enviada sem texto legivel]',
                $image,
                idempotencyKey: $idempotencyKey,
            );
            $storedUserMessage->update(['status' => 'completed']);

            return $this->storeAssistantMessage($chat, self::IMAGE_WITHOUT_TEXT_MESSAGE, [
                'stage' => 'awaiting_problem_details',
                'reply_to' => $idempotencyKey,
            ]);
        }

        $storedUserMessage = $this->storeUserMessage(
            $chat,
            $effectiveMessage,
            $image,
            idempotencyKey: $idempotencyKey,
        );

        try {
            $documents = $this->vectorSearchService->search(
                $effectiveMessage,
                $chat->user_id,
                $chat->software,
            );

            if ($documents === []) {
                $storedUserMessage->update(['status' => 'completed']);

                return $this->storeAssistantMessage($chat, self::NOT_FOUND_MESSAGE, [
                    'stage' => 'awaiting_external_search_consent',
                    'reply_to' => $idempotencyKey,
                ]);
            }

            $messages = $this->promptManager->build(
                history: $chat->messages()->latest('id')->limit(100)->get()->sortBy('id')->values(),
                software: $chat->software,
                documents: array_map(
                    fn (array $document) => ['title' => $document['title'], 'content' => $document['content']],
                    $documents,
                ),
            );

            $response = $this->aiService->complete($messages);
            $storedUserMessage->update(['status' => 'completed']);

            return $this->storeAssistantMessage(
                $chat,
                $response->content ?? 'Nao foi possivel gerar uma resposta no momento.',
                [
                    'source' => 'internal_knowledge_base',
                    'matched_documents' => array_column($documents, 'id'),
                    'reply_to' => $idempotencyKey,
                ],
            );
        } catch (\Throwable $exception) {
            $storedUserMessage->update(['status' => 'failed']);

            throw $exception;
        }
    }

    private function handleExternalSearchDecision(Chat $chat, bool $consented, ?string $idempotencyKey): ChatMessage
    {
        $lastAssistantMessage = $chat->messages()
            ->where('role', ChatMessage::ROLE_ASSISTANT)
            ->latest('id')
            ->first();

        if ($lastAssistantMessage?->metadata['stage'] !== 'awaiting_external_search_consent') {
            throw ValidationException::withMessages([
                'external_search_consent' => 'Esta conversa nao esta aguardando autorizacao para pesquisa externa.',
            ]);
        }

        if (! $consented) {
            $userDecision = $this->storeUserMessage($chat, self::DECLINE_MESSAGE, null, [
                'event' => 'external_search_declined',
            ], $idempotencyKey);
            $userDecision->update(['status' => 'completed']);

            return $this->storeAssistantMessage($chat, self::DECLINED_MESSAGE, [
                'stage' => 'external_search_declined',
                'reply_to' => $idempotencyKey,
            ]);
        }

        $originalMessage = $chat->messages()
            ->where('role', ChatMessage::ROLE_USER)
            ->where('content', '!=', '')
            ->latest('id')
            ->value('content');

        if ($originalMessage === null) {
            throw ValidationException::withMessages([
                'external_search_consent' => 'Nao foi encontrada uma mensagem anterior para pesquisar.',
            ]);
        }

        $userDecision = $this->storeUserMessage($chat, self::CONSENT_MESSAGE, null, [
            'event' => 'external_search_consented',
        ], $idempotencyKey);

        $query = trim(implode(' ', array_filter([
            $chat->software !== null ? "Software {$chat->software}." : null,
            $originalMessage,
        ])));

        try {
            $toolResult = $this->webSearchTool->handle(['query' => $query]);
            $userDecision->update(['status' => 'completed']);
        } catch (\Throwable $exception) {
            $userDecision->update(['status' => 'failed']);

            throw $exception;
        }

        return $this->storeAssistantMessage($chat, $toolResult->content, [
            'source' => 'external_search',
            'sources' => $toolResult->sources,
            'stage' => 'external_search_completed',
            'reply_to' => $idempotencyKey,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function storeUserMessage(
        Chat $chat,
        string $content,
        ?UploadedFile $image,
        ?array $metadata = null,
        ?string $idempotencyKey = null,
    ): ChatMessage {
        $attachmentDocumentId = null;

        if ($image !== null) {
            $path = $image->store('chat-images', 'local');
            $attachment = Document::create([
                'user_id' => $chat->user_id,
                'chat_id' => $chat->id,
                'type' => Document::TYPE_CHAT_IMAGE,
                'name' => $image->getClientOriginalName(),
                'file_path' => $path,
                'disk' => 'local',
                'mime_type' => $image->getMimeType(),
                'status' => 'indexed',
                'file_size' => $image->getSize(),
            ]);
            $attachmentDocumentId = $attachment->id;
            $metadata = [...($metadata ?? []), 'has_image' => true, 'attachment_id' => $attachment->id];
        }

        return $chat->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => $content,
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'attachment_document_id' => $attachmentDocumentId,
            'metadata' => $metadata,
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
