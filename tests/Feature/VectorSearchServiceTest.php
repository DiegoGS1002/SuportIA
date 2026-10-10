<?php

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\Search\EmbeddingWriter;
use App\Services\Search\VectorSearchService;
use App\Services\Search\VectorTypeDetector;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Factory as OpenAIFactory;

uses(RefreshDatabase::class);

/**
 * Builds a real OpenAIEmbeddingClient backed by a fake HTTP transport that
 * always returns the given embedding vector, so no real network calls are
 * made during tests (the client class is final and can't be mocked).
 *
 * @param  array<int, float>  $embedding
 */
function fakeEmbeddingClient(array $embedding): OpenAIEmbeddingClient
{
    $mockHandler = new MockHandler(array_fill(0, 50, new Response(200, [], json_encode([
        'object' => 'list',
        'data' => [
            ['object' => 'embedding', 'index' => 0, 'embedding' => $embedding],
        ],
        'model' => 'text-embedding-3-large',
        'usage' => ['prompt_tokens' => 1, 'total_tokens' => 1],
    ]))));

    $guzzleClient = new GuzzleClient(['handler' => HandlerStack::create($mockHandler)]);

    $openAiClient = (new OpenAIFactory)
        ->withApiKey('fake-key-for-tests')
        ->withHttpClient($guzzleClient)
        ->make();

    return new OpenAIEmbeddingClient(
        client: $openAiClient,
        model: 'text-embedding-3-large',
        dimensions: count($embedding),
    );
}

it('filters out results whose distance exceeds the configured max distance', function () {
    $user = User::factory()->create();
    $document = Document::create(['user_id' => $user->id, 'name' => 'doc.txt', 'file_path' => 'x', 'file_size' => 1, 'status' => 'indexed']);

    // Orthogonal vectors: an exact match (distance ~0) and a dissimilar one
    // (distance ~1), so we can assert the max distance threshold behavior
    // without depending on real embedding calibration.
    $matching = array_fill(0, 4, 1.0);
    $distant = [1.0, -1.0, 1.0, -1.0];

    $knowledgeBase = KnowledgeBase::create([
        'document_id' => $document->id,
        'software' => 'home',
        'title' => 'Relevante',
        'content' => 'Conteudo relevante para o teste de busca vetorial.',
    ]);

    app(EmbeddingWriter::class)->store($knowledgeBase->id, $matching);

    $service = new VectorSearchService(
        embeddingClient: fakeEmbeddingClient($matching),
        vectorTypeDetector: app(VectorTypeDetector::class),
        topK: 3,
        maxDistance: 0.6,
    );

    $results = $service->search('pergunta relevante', $user->id, 'home');

    expect($results)->toHaveCount(1);
    expect($results[0]['title'])->toBe('Relevante');
});

it('returns no results when the best match exceeds the max distance', function () {
    $user = User::factory()->create();
    $document = Document::create(['user_id' => $user->id, 'name' => 'doc.txt', 'file_path' => 'x', 'file_size' => 1, 'status' => 'indexed']);

    $stored = [1.0, -1.0, 1.0, -1.0];
    $query = array_fill(0, 4, 1.0);

    $knowledgeBase = KnowledgeBase::create([
        'document_id' => $document->id,
        'software' => 'home',
        'title' => 'Irrelevante',
        'content' => 'Conteudo sem relacao com a pergunta feita.',
    ]);

    app(EmbeddingWriter::class)->store($knowledgeBase->id, $stored);

    $service = new VectorSearchService(
        embeddingClient: fakeEmbeddingClient($query),
        vectorTypeDetector: app(VectorTypeDetector::class),
        topK: 3,
        maxDistance: 0.1,
    );

    $results = $service->search('pergunta nao relacionada', $user->id, 'home');

    expect($results)->toBe([]);
});

it('searches indexed knowledge and exported documents only for their owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $embedding = array_fill(0, 4, 1.0);

    foreach ([
        [$owner->id, Document::TYPE_KNOWLEDGE_BASE, 'indexed', 'Manual interno'],
        [$owner->id, Document::TYPE_CHAT_EXPORT, 'indexed', 'Atendimento exportado'],
        [$owner->id, Document::TYPE_KNOWLEDGE_BASE, 'processing', 'Ainda processando'],
        [$otherUser->id, Document::TYPE_KNOWLEDGE_BASE, 'indexed', 'Documento de outro usuario'],
    ] as [$userId, $type, $status, $title]) {
        $document = Document::create([
            'user_id' => $userId,
            'type' => $type,
            'status' => $status,
            'name' => $title.'.pdf',
            'file_path' => 'x',
            'file_size' => 1,
        ]);
        $chunk = KnowledgeBase::create([
            'document_id' => $document->id,
            'software' => 'home',
            'title' => $title,
            'content' => 'Conteudo pesquisavel deste documento para validacao.',
        ]);
        app(EmbeddingWriter::class)->store($chunk->id, $embedding);
    }

    $service = new VectorSearchService(
        embeddingClient: fakeEmbeddingClient($embedding),
        vectorTypeDetector: app(VectorTypeDetector::class),
        topK: 10,
        maxDistance: 0.6,
    );

    $results = $service->search('conteudo pesquisavel', $owner->id, 'home');

    expect(array_column($results, 'title'))->toEqualCanonicalizing([
        'Manual interno',
        'Atendimento exportado',
    ]);
});
