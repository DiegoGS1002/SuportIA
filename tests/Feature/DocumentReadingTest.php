<?php

use App\Jobs\ProcessKnowledgeBaseDocument;
use App\Models\Chat;
use App\Models\Document;
use App\Models\User;
use App\Services\ChatToPdfExporter;
use App\Services\DocumentProcessor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->processor = app(DocumentProcessor::class);
});

it('extracts searchable text from txt and markdown files', function (string $filename) {
    $content = 'Procedimento verificavel para corrigir o erro 539 no sistema.';
    Storage::disk('local')->put($filename, $content);

    expect($this->processor->extractText(Storage::disk('local')->path($filename)))
        ->toContain('erro 539');
})->with(['manual.txt', 'manual.md']);

it('extracts searchable text from docx files', function () {
    $path = Storage::disk('local')->path('manual.docx');
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('word/document.xml', <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:body><w:p><w:r><w:t>Solucao confirmada para o erro DOCX 784.</w:t></w:r></w:p></w:body>
        </w:document>
        XML);
    $zip->close();

    expect($this->processor->extractText($path))->toContain('DOCX 784');
});

it('extracts searchable text from generated pdf files', function () {
    $pdf = Pdf::loadHTML('<html><body><p>Procedimento PDF para resolver o codigo 991.</p></body></html>');
    Storage::disk('local')->put('manual.pdf', $pdf->output());

    expect($this->processor->extractText(Storage::disk('local')->path('manual.pdf')))
        ->toContain('codigo 991');
});

it('splits long text without losing its beginning or end', function () {
    $text = 'INICIO '.str_repeat('conteudo de verificacao. ', 200).' FINAL';
    $chunks = $this->processor->chunkText($text);

    expect($chunks)->toHaveCount(4)
        ->and($chunks[0])->toStartWith('INICIO')
        ->and($chunks[array_key_last($chunks)])->toEndWith('FINAL');
});

it('queues exported conversations for text extraction and indexing', function () {
    Queue::fake();
    $user = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user->id,
        'software' => 'home',
        'title' => 'Erro de emissao',
    ]);
    $chat->messages()->create([
        'role' => 'user',
        'content' => 'O sistema apresenta o erro exportado 456.',
    ]);

    $document = app(ChatToPdfExporter::class)->export($chat, 'Atendimento resolvido', $user->id);

    expect($document->type)->toBe(Document::TYPE_CHAT_EXPORT)
        ->and($document->status)->toBe('uploaded')
        ->and($document->software)->toBe('home')
        ->and($document->title)->toBe('Atendimento resolvido')
        ->and($this->processor->extractText(Storage::disk('local')->path($document->file_path)))
        ->toContain('erro exportado 456');

    Queue::assertPushed(
        ProcessKnowledgeBaseDocument::class,
        fn (ProcessKnowledgeBaseDocument $job): bool => $job->documentId === $document->id,
    );
});

it('re-indexes a legacy exported conversation', function () {
    Queue::fake();
    $user = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user->id,
        'software' => 'stella',
        'title' => 'Atendimento antigo',
    ]);
    $document = Document::create([
        'user_id' => $user->id,
        'chat_id' => $chat->id,
        'type' => Document::TYPE_CHAT_EXPORT,
        'name' => 'rejeicao_778.pdf',
        'file_path' => 'documents/rejeicao_778.pdf',
        'disk' => 'local',
        'mime_type' => 'application/pdf',
        'status' => 'indexed',
        'chunks_count' => 0,
        'file_size' => 100,
    ]);

    $this->actingAs($user)
        ->postJson("/api/documents/{$document->id}/retry")
        ->assertAccepted();

    expect($document->fresh()->status)->toBe('uploaded')
        ->and($document->fresh()->software)->toBe('stella')
        ->and($document->fresh()->title)->toBe('rejeicao_778');

    Queue::assertPushed(
        ProcessKnowledgeBaseDocument::class,
        fn (ProcessKnowledgeBaseDocument $job): bool => $job->documentId === $document->id,
    );
});
