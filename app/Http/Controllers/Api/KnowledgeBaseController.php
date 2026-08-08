<?php

namespace App\Http\Controllers\Api;

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBaseStoreRequest;
use App\Models\Document;
use App\Models\KnowledgeBase;
use App\Services\DocumentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly OpenAIEmbeddingClient $embeddingClient,
        private readonly DocumentProcessor $documentProcessor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Esta parte do código precisa ser ajustada para buscar os documentos originais,
        // e não mais os chunks da knowledge_bases diretamente.
        // Por enquanto, vou manter como está, mas é um ponto de atenção.
        $documents = KnowledgeBase::query()
            ->select(['id', 'software', 'title', 'content', 'created_at'])
            ->when($request->string('software')->isNotEmpty(), fn ($query) => $query->where('software', $request->string('software')->toString()))
            ->latest()
            ->paginate(20);

        return response()->json($documents);
    }

    public function store(KnowledgeBaseStoreRequest $request): JsonResponse
    {
        try {
            $uploadedFile = $request->file('file');
            $software = $request->validated('software');
            $title = $request->validated('title');

            $filePath = $uploadedFile->store('documents', 'public');
            $fullPath = Storage::disk('public')->path($filePath);

            $document = Document::create([
                'user_id' => auth()->id(),
                'chat_id' => null,
                'name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $uploadedFile->getSize(),
            ]);

            $fullContent = $this->documentProcessor->extractText($fullPath);

            if (empty($fullContent) || !$this->documentProcessor->isValidContent($fullContent)) {
                Log::warning("Documento '{$uploadedFile->getClientOriginalName()}' não contém texto extraível ou conteúdo inválido. Será salvo sem embeddings.");
                return response()->json([
                    'message' => 'Documento salvo, mas não contém texto extraível ou é um arquivo escaneado.',
                    'document_id' => $document->id,
                    'warning' => 'Embeddings não foram gerados'
                ], 201);
            }

            $chunks = $this->documentProcessor->chunkText($fullContent);

            foreach ($chunks as $index => $chunk) {
                $embedding = $this->embeddingClient->embed($chunk);

                KnowledgeBase::create([
                    'document_id' => $document->id,
                    'software' => $software,
                    'title' => $title . ' (Parte ' . ($index + 1) . ')',
                    'content' => $chunk,
                    'embedding' => $embedding,
                ]);
            }

            return response()->json([
                'message' => 'Documento e embeddings processados com sucesso.',
                'document_id' => $document->id
            ], 201);
        } catch (\Throwable $e) {
            if (isset($document) && $document->exists()) {
                $document->delete();
            }
            if (isset($filePath) && Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
            Log::error("Erro ao processar arquivo: {$e->getMessage()}");
            return response()->json(['error' => 'Erro ao processar arquivo: ' . $e->getMessage()], 422);
        }
    }

    public function destroy(KnowledgeBase $knowledgeBase): JsonResponse
    {
        // Ao destruir um chunk, você pode querer destruir o documento original se for o último chunk
        // Ou, mais provavelmente, você terá um endpoint para destruir o Document original,
        // e o cascadeOnDelete cuidará dos chunks.
        $knowledgeBase->delete();

        return response()->json(['message' => 'Entrada da base de conhecimento excluída com sucesso.']);
    }
}
