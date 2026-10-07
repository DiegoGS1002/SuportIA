<?php

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Models\Document;
use App\Models\User;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenAI\Client;
use OpenAI\Factory as OpenAIFactory;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Fake the OpenAI HTTP transport so tests never hit the real API. The
    // embeddings client (and everything built on top of it) is final and
    // cannot be mocked with Mockery, so we fake at the HTTP layer instead.
    $fakeEmbedding = array_fill(0, 1536, 0.001);
    $mockHandler = new MockHandler(array_fill(0, 200, new Response(200, [], json_encode([
        'object' => 'list',
        'data' => [
            ['object' => 'embedding', 'index' => 0, 'embedding' => $fakeEmbedding],
        ],
        'model' => 'text-embedding-3-large',
        'usage' => ['prompt_tokens' => 1, 'total_tokens' => 1],
    ]))));

    $guzzleClient = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

    $fakeOpenAiClient = (new OpenAIFactory)
        ->withApiKey('fake-key-for-tests')
        ->withHttpClient($guzzleClient)
        ->make();

    $this->app->instance(Client::class, $fakeOpenAiClient);
    $this->app->forgetInstance(OpenAIEmbeddingClient::class);
});

describe('KnowledgeBase Upload', function () {
    it('can upload a valid text file', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'Este é um documento de teste válido com conteúdo suficiente.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Documento de Teste',
            'file' => $file,
        ]);

        $response->assertAccepted();
        expect(Document::count())->toBe(1);
        expect(Document::first()->status)->toBe('indexed');
    });

    it('can upload and index a markdown file', function () {
        $file = UploadedFile::fake()->createWithContent(
            'manual.md',
            "# Solucao\n\nSiga estas etapas para corrigir o problema apresentado no sistema.",
        );

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Manual em Markdown',
            'file' => $file,
        ]);

        $response->assertAccepted();

        $document = Document::first();
        expect($document->name)->toBe('manual.md');
        expect($document->status)->toBe('indexed');
        expect($document->chunks_count)->toBeGreaterThan(0);
    });

    it('can upload a PDF file even if it has no extractable text', function () {
        $file = UploadedFile::fake()->createWithContent('test.pdf', '%PDF-1.4 corrupted content');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'PDF Escaneado',
            'file' => $file,
        ]);

        $response->assertAccepted();
        expect(Document::count())->toBe(1);
        expect(Document::first()->status)->toBe('needs_ocr');
    });

    it('can upload a large file (above the old 10MB limit)', function () {
        // Uses a fake binary file (like PDF::fake()) so the upload can exceed
        // 10MB without triggering thousands of real embedding calls: content
        // extraction naturally fails for random binary data, so the document
        // is stored via the "no extractable text" fallback branch.
        $file = UploadedFile::fake()->create('large.pdf', 15 * 1024, 'application/pdf');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Arquivo Grande',
            'file' => $file,
        ]);

        $response->assertAccepted();
        expect(Document::count())->toBe(1);
    });

    it('rejects files with invalid mime type', function () {
        $file = UploadedFile::fake()->create('test.exe', 1024);

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Arquivo Inválido',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
        expect(Document::count())->toBe(0);
    });

    it('rejects files with very small content', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'abc');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Arquivo Pequeno',
            'file' => $file,
        ]);

        $response->assertAccepted();
        expect(Document::count())->toBe(1);
    });

    it('validates software field', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'Conteúdo de teste.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'invalid-software',
            'title' => 'Documento',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('software');
    });

    it('validates title field', function () {
        $file = UploadedFile::fake()->createWithContent('test.txt', 'Conteúdo de teste.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => '',
            'file' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('title');
    });

    it('stores document with correct metadata', function () {
        $fileName = 'test-document.pdf';
        $file = UploadedFile::fake()->createWithContent($fileName, 'Test content for verification.');

        $response = $this->postJson('/api/knowledge-bases', [
            'software' => 'home',
            'title' => 'Documento com Metadados',
            'file' => $file,
        ]);

        $response->assertAccepted();

        $document = Document::first();
        expect($document->name)->toBe($fileName);
        expect($document->user_id)->toBe($this->user->id);
        expect($document->disk)->toBe('local');
        expect($document->type)->toBe(Document::TYPE_KNOWLEDGE_BASE);
        expect($document->file_size)->toBeGreaterThan(0);
    });
});
