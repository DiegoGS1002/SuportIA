<?php

namespace App\Http\Controllers\Api;

use App\AI\Agents\SupportAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatMessageRequest;
use App\Http\Requests\ChatStoreRequest;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->when($request->user(), fn ($query) => $query->where('user_id', $request->user()->id))
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
            'user_id' => $request->user()?->id,
            'software' => $request->validated('software'),
            'title' => $request->validated('title'),
        ]);

        return response()->json($chat, 201);
    }

    /**
     * Returns a conversation with its full message history.
     */
    public function show(Chat $chat): JsonResponse
    {
        return response()->json($chat->load('messages'));
    }

    /**
     * Sends a new message (text and/or image) to the SupportAgent and
     * returns the assistant's reply.
     */
    public function sendMessage(ChatMessageRequest $request, Chat $chat): JsonResponse
    {
        $assistantMessage = $this->supportAgent->handle(
            chat: $chat,
            userMessage: $request->validated('message'),
            image: $request->file('image'),
            externalSearchConsent: (bool) $request->boolean('external_search_consent'),
            externalSearchDeclined: (bool) $request->boolean('external_search_declined'),
        );

        return response()->json([
            'chat' => $chat->fresh('messages'),
            'message' => $assistantMessage,
        ], 201);
    }

    /**
     * Deletes a conversation and all its messages.
     */
    public function destroy(Chat $chat): JsonResponse
    {
        $chat->delete();

        return response()->json(['message' => 'Conversa excluida com sucesso.']);
    }
}
