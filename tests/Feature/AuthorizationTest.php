<?php

use App\Models\Chat;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('requires authentication for protected api routes', function () {
    $this->getJson('/api/chats')->assertUnauthorized();
    $this->getJson('/api/documents')->assertUnauthorized();
    $this->getJson('/api/knowledge-bases')->assertUnauthorized();
});

it('redirects unauthenticated web requests to the login screen', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/chat')->assertRedirect('/login');
    $this->get('/chat/123')->assertRedirect('/login');
    $this->get('/login')->assertOk();
});

it('allows authenticated users to open protected spa routes', function () {
    $this->actingAs(User::factory()->create())
        ->get('/chat')
        ->assertOk();
});

it('authenticates a user and keeps the session available to protected routes', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk();

    $this->assertAuthenticatedAs($user);
    $this->get('/chat')->assertOk();
});

it('returns a validation error when login credentials are invalid', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'senha-incorreta',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('does not allow a user to access another users chat', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $chat = Chat::create(['user_id' => $owner->id, 'software' => 'home']);

    $this->actingAs($otherUser)
        ->getJson("/api/chats/{$chat->id}")
        ->assertForbidden();
});

it('does not allow a user to download another users document', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $document = Document::create([
        'user_id' => $owner->id,
        'type' => Document::TYPE_CHAT_EXPORT,
        'name' => 'private.pdf',
        'file_path' => 'documents/private.pdf',
        'disk' => 'local',
        'mime_type' => 'application/pdf',
        'status' => 'indexed',
        'file_size' => 10,
    ]);

    $this->actingAs($otherUser)
        ->getJson("/api/documents/{$document->id}/download")
        ->assertNotFound();
});

it('allows a user to download their exported document', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Storage::disk('local')->put('documents/export.pdf', 'pdf-content');
    $document = Document::create([
        'user_id' => $user->id,
        'type' => Document::TYPE_CHAT_EXPORT,
        'name' => 'export.pdf',
        'file_path' => 'documents/export.pdf',
        'disk' => 'local',
        'mime_type' => 'application/pdf',
        'status' => 'indexed',
        'file_size' => 11,
    ]);

    $this->actingAs($user)
        ->get("/api/documents/{$document->id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('allows initial setup only while there are no users', function () {
    $payload = [
        'name' => 'Administrador',
        'email' => 'admin@example.com',
        'password' => 'uma-senha-segura-123',
        'password_confirmation' => 'uma-senha-segura-123',
    ];

    $this->postJson('/api/setup', $payload)->assertCreated();
    $this->postJson('/api/setup', $payload)->assertConflict();
});

it('accepts an eight character password during initial setup', function () {
    $payload = [
        'name' => 'Administrador',
        'email' => 'admin@example.com',
        'password' => '12345678',
        'password_confirmation' => '12345678',
    ];

    $this->postJson('/api/setup', $payload)->assertCreated();
    $this->assertAuthenticated();
});

it('rejects a password shorter than eight characters during initial setup', function () {
    $payload = [
        'name' => 'Administrador',
        'email' => 'admin@example.com',
        'password' => '1234567',
        'password_confirmation' => '1234567',
    ];

    $this->postJson('/api/setup', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('deletes a knowledge document with its chunks and private file', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Storage::disk('local')->put('knowledge-base/manual.txt', 'conteudo');

    $document = Document::create([
        'user_id' => $user->id,
        'type' => Document::TYPE_KNOWLEDGE_BASE,
        'name' => 'manual.txt',
        'file_path' => 'knowledge-base/manual.txt',
        'disk' => 'local',
        'mime_type' => 'text/plain',
        'software' => 'home',
        'title' => 'Manual',
        'status' => 'indexed',
        'file_size' => 8,
    ]);
    $document->knowledgeBases()->create([
        'software' => 'home',
        'title' => 'Manual (Parte 1)',
        'content' => 'Conteudo indexado suficientemente longo.',
    ]);

    $this->actingAs($user)
        ->deleteJson("/api/knowledge-bases/{$document->id}")
        ->assertOk();

    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    $this->assertDatabaseCount('knowledge_bases', 0);
    Storage::disk('local')->assertMissing('knowledge-base/manual.txt');
});
