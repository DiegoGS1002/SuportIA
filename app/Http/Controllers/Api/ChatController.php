<?php

namespace App\Http\Controllers\Api;

use App\AI\Agents\SupportAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatMessageRequest;
use App\Http\Requests\ChatStoreRequest;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Services\ChatToPdfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Entry point of the AI support flow: receives the user message/image
 * and dispatches it to the SupportAgent orchestrator.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly SupportAgent $supportAgent,
    ) {}

    /**
     * List the conversations, most recent first.
     */
    public function index(Request $request): JsonResponse
    {
        $chats = Chat::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($chats);
    }

    /**
     * Starts a new conversation.
     */
    public function store(ChatStoreRequest $request): JsonResponse
    {
        $chat = Chat::create([
            'user_id' => $request->user()->id,
            'software' => $request->validated('software'),
            'title' => $request->validated('title'),
        ]);

        return response()->json($chat, 201);
    }

    /**
     * Returns a conversation with its full message history.
     */
    public function show(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('view', $chat);

        $messages = $chat->messages()
            ->latest('id')
            ->limit(100)
            ->get()
            ->sortBy('id')
            ->values();

        $chat->setRelation('messages', $messages);
        $chat->setAttribute('has_more_messages', $chat->messages()->count() > $messages->count());

        return response()->json($chat);
    }

    /**
     * Sends a new message (text and/or image) to the SupportAgent and
     * returns the assistant's reply.
     */
    public function sendMessage(ChatMessageRequest $request, Chat $chat): JsonResponse
    {
        $this->authorize('update', $chat);

        $idempotencyKey = $request->validated('client_message_id');

        if ($idempotencyKey !== null) {
            $existingUserMessage = $chat->messages()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingUserMessage !== null) {
                $assistantMessage = $chat->messages()
                    ->where('role', 'assistant')
                    ->where('id', '>', $existingUserMessage->id)
                    ->first();

                if ($assistantMessage === null) {
                    return response()->json([
                        'error' => 'Esta mensagem ainda esta sendo processada.',
                        'code' => 'message_processing',
                    ], 409);
                }

                return response()->json([
                    'chat' => $this->withRecentMessages($chat),
                    'message' => $assistantMessage,
                    'duplicate' => true,
                ]);
            }
        }

        try {
            $assistantMessage = $this->supportAgent->handle(
                chat: $chat,
                userMessage: $request->validated('message'),
                image: $request->file('image'),
                externalSearchConsent: (bool) $request->boolean('external_search_consent'),
                externalSearchDeclined: (bool) $request->boolean('external_search_declined'),
                idempotencyKey: $idempotencyKey,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Falha ao processar mensagem do chat.', [
                'chat_id' => $chat->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Nao foi possivel processar a mensagem agora. Tente novamente.',
                'code' => 'chat_processing_failed',
            ], 503);
        }

        return response()->json([
            'chat' => $this->withRecentMessages($chat),
            'message' => $assistantMessage,
        ], 201);
    }

    /**
     * Deletes a conversation and all its messages.
     */
    public function destroy(Request $request, Chat $chat): JsonResponse
    {
        $this->authorize('delete', $chat);

        foreach ($chat->documents as $document) {
            Storage::disk($document->disk)->delete($document->file_path);
        }

        $chat->delete();

        return response()->json(['message' => 'Conversa excluida com sucesso.']);
    }

    public function downloadAttachment(Request $request, Chat $chat, ChatMessage $message)
    {
        $this->authorize('view', $chat);
        abort_unless($message->chat_id === $chat->id && $message->attachment !== null, 404);

        $document = $message->attachment;
        abort_unless(Storage::disk($document->disk)->exists($document->file_path), 404);

        return Storage::disk($document->disk)->download(
            $document->file_path,
            $document->name,
            ['Content-Type' => $document->mime_type ?? 'application/octet-stream'],
        );
    }

    /**
     * Exports a conversation to PDF and stores it as a document.
     */
    public function exportPdf(Request $request, Chat $chat, ChatToPdfExporter $exporter): JsonResponse
    {
        $this->authorize('view', $chat);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $document = $exporter->export(
                $chat,
                $validated['name'],
                $request->user()->id
            );

            return response()->json([
                'message' => 'Documento exportado com sucesso.',
                'document' => $document,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao exportar documento: '.$e->getMessage(),
            ], 500);
        }
    }

    private function withRecentMessages(Chat $chat): Chat
    {
        $chat = $chat->fresh();
        $messages = $chat->messages()
            ->latest('id')
            ->limit(100)
            ->get()
            ->sortBy('id')
            ->values();

        $chat->setRelation('messages', $messages);
        $chat->setAttribute('has_more_messages', $chat->messages()->count() > $messages->count());

        return $chat;
    }
}
