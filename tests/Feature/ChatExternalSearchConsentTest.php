<?php

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Client;
use OpenAI\Factory as OpenAIFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function chatAwaitingExternalSearchConsent(User $user): Chat
{
    $chat = Chat::create([
        'user_id' => $user->id,
        'software' => 'home',
        'title' => 'Erro ao emitir nota',
    ]);

    $chat->messages()->createMany([
        [
            'role' => ChatMessage::ROLE_USER,
            'content' => 'O sistema apresenta o erro 539 ao emitir a nota.',
        ],
        [
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => 'Deseja autorizar uma pesquisa externa?',
            'metadata' => ['stage' => 'awaiting_external_search_consent'],
        ],
    ]);

    return $chat;
}

it('accepts an external search refusal without requiring a text message', function () {
    $chat = chatAwaitingExternalSearchConsent($this->user);

    $response = $this->postJson("/api/chats/{$chat->id}/messages", [
        'external_search_declined' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message.metadata.stage', 'external_search_declined');

    $messages = $chat->fresh()->messages;

    expect($messages)->toHaveCount(4)
        ->and($messages[2]->content)->not->toBeEmpty()
        ->and($messages[2]->metadata['event'])->toBe('external_search_declined');
});

it('uses the original user question after external search consent', function () {
    $mockHandler = new MockHandler([
        new Response(200, [], json_encode([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'created' => 1,
            'model' => 'gpt-4o',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Resultado externo para o erro 539.',
                    'annotations' => [[
                        'type' => 'url_citation',
                        'url_citation' => [
                            'start_index' => 0,
                            'end_index' => 9,
                            'title' => 'Fonte tecnica',
                            'url' => 'https://example.com/erro-539',
                        ],
                    ]],
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 10,
                'total_tokens' => 20,
            ],
        ], JSON_THROW_ON_ERROR)),
    ]);

    $client = (new OpenAIFactory)
        ->withApiKey('fake-key-for-tests')
        ->withHttpClient(new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]))
        ->make();

    $this->app->instance(Client::class, $client);

    $chat = chatAwaitingExternalSearchConsent($this->user);

    $response = $this->postJson("/api/chats/{$chat->id}/messages", [
        'external_search_consent' => true,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message.content', 'Resultado externo para o erro 539.')
        ->assertJsonPath('message.metadata.source', 'external_search')
        ->assertJsonPath('message.metadata.sources.0.url', 'https://example.com/erro-539')
        ->assertJsonPath('message.metadata.stage', 'external_search_completed');

    $messages = $chat->fresh()->messages;

    expect($messages)->toHaveCount(4)
        ->and($messages[2]->content)->not->toBeEmpty()
        ->and($messages[2]->metadata['event'])->toBe('external_search_consented');
});

it('rejects a search decision when the chat is not awaiting consent', function () {
    $chat = Chat::create(['user_id' => $this->user->id, 'software' => 'home']);

    $response = $this->postJson("/api/chats/{$chat->id}/messages", [
        'external_search_consent' => true,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('external_search_consent');
});

it('rejects consent and refusal sent at the same time', function () {
    $chat = chatAwaitingExternalSearchConsent($this->user);

    $response = $this->postJson("/api/chats/{$chat->id}/messages", [
        'external_search_consent' => true,
        'external_search_declined' => true,
    ]);

    $response->assertUnprocessable();
});

it('does not process the same client message twice', function () {
    $chat = chatAwaitingExternalSearchConsent($this->user);
    $clientMessageId = fake()->uuid();

    $payload = [
        'external_search_declined' => true,
        'client_message_id' => $clientMessageId,
    ];

    $this->postJson("/api/chats/{$chat->id}/messages", $payload)->assertCreated();

    $this->postJson("/api/chats/{$chat->id}/messages", $payload)
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    expect($chat->fresh()->messages)->toHaveCount(4);
});
