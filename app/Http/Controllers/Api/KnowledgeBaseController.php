<?php

namespace App\Http\Controllers\Api;

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBaseStoreRequest;
use App\Models\KnowledgeBase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * Read-only endpoint to list the internal knowledge base documents
 * used by the RAG (VectorSearchService), so the front-end can display
 * what solutions are currently indexed.
 */
class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly OpenAIEmbeddingClient $embeddingClient,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $documents = KnowledgeBase::query()
            ->select(['id', 'software', 'title', 'content', 'created_at'])
            ->when($request->string('software')->isNotEmpty(), fn ($query) => $query->where('software', $request->string('software')->toString()))
            ->latest()
            ->paginate(20);

        return response()->json($documents);
    }

    /**
     * Uploads and indexes a document (PDF, Word, TXT) into the knowledge base.
     */
    public function store(KnowledgeBaseStoreRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $content = $this->extractTextFromFile($file);

        if (trim($content) === '') {
            return response()->json(['error' => 'Nao foi possivel extrair texto do arquivo.'], 422);
        }

        $embedding = $this->embeddingClient->embed($content);

        $document = KnowledgeBase::create([
            'software' => $request->validated('software'),
            'title' => $request->validated('title'),
            'content' => $content,
            'embedding' => $embedding,
        ]);

        return response()->json($document, 201);
    }

    /**
     * Deletes a knowledge base document.
     */
    public function destroy(KnowledgeBase $knowledgeBase): JsonResponse
    {
        $knowledgeBase->delete();

        return response()->json(['message' => 'Documento excluido com sucesso.']);
    }

    private function extractTextFromFile($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'txt' => file_get_contents($file->getRealPath()),
            'pdf' => $this->extractFromPdf($file->getRealPath()),
            'doc', 'docx' => $this->extractFromWord($file->getRealPath()),
            default => '',
        };
    }

    private function extractFromPdf(string $path): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);

            return $pdf->getText();
        } catch (\Throwable) {
            return '';
        }
    }

    private function extractFromWord(string $path): string
    {
        try {
            if (str_ends_with($path, '.docx')) {
                $zip = new \ZipArchive;

                if ($zip->open($path) === true) {
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();

                    if ($xml !== false) {
                        return strip_tags($xml);
                    }
                }
            }

            return '';
        } catch (\Throwable) {
            return '';
        }
    }
}

