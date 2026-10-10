<?php

namespace App\Jobs;

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Models\Document;
use App\Services\DocumentProcessor;
use App\Services\Search\EmbeddingWriter;
use App\Services\Vision\OCRService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessKnowledgeBaseDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $documentId) {}

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        DocumentProcessor $processor,
        OCRService $ocrService,
        OpenAIEmbeddingClient $embeddingClient,
        EmbeddingWriter $embeddingWriter,
    ): void {
        $document = Document::findOrFail($this->documentId);
        $document->update(['status' => 'processing', 'error_message' => null]);

        $path = Storage::disk($document->disk)->path($document->file_path);
        $content = $processor->extractText($path);

        if (! $processor->isValidContent($content) && $document->mime_type === 'application/pdf') {
            $content = $ocrService->extractTextFromPdf($path);
        }

        if (! $processor->isValidContent($content)) {
            $document->knowledgeBases()->delete();
            $document->update([
                'status' => 'needs_ocr',
                'error_message' => 'Nao foi possivel extrair texto legivel deste arquivo.',
                'chunks_count' => 0,
            ]);

            return;
        }

        $chunks = array_slice($processor->chunkText($content), 0, 100);
        $embeddings = [];

        foreach (array_chunk($chunks, 50, true) as $chunkBatch) {
            $embeddings += array_combine(
                array_keys($chunkBatch),
                $embeddingClient->embedMany(array_values($chunkBatch)),
            );
        }

        DB::transaction(function () use ($document, $chunks, $embeddings, $embeddingWriter): void {
            $document->knowledgeBases()->delete();

            foreach ($chunks as $index => $chunk) {
                $knowledgeBase = $document->knowledgeBases()->create([
                    'software' => $document->software,
                    'title' => $document->title.' (Parte '.($index + 1).')',
                    'content' => $chunk,
                ]);

                $embeddingWriter->store($knowledgeBase->id, $embeddings[$index]);
            }

            $document->update([
                'status' => 'indexed',
                'chunks_count' => count($chunks),
                'error_message' => null,
            ]);
        });
    }

    public function failed(?Throwable $exception): void
    {
        Document::whereKey($this->documentId)->update([
            'status' => 'failed',
            'error_message' => 'Falha ao processar o documento. Tente novamente.',
        ]);

        Log::error('Falha definitiva ao processar documento da base.', [
            'document_id' => $this->documentId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
