<?php

namespace App\Http\Controllers\Api;

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBaseStoreRequest;
use App\Models\KnowledgeBase;
use App\Services\DocumentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeBaseController extends Controller
{
    public function __construct(
        private readonly OpenAIEmbeddingClient $embeddingClient,
        private readonly DocumentProcessor $documentProcessor,
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

    public function store(KnowledgeBaseStoreRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $content = $this->documentProcessor->extractText($file);

            if (!$this->documentProcessor->isValidContent($content)) {
                return response()->json(['error' => 'Não foi possível extrair texto válido do arquivo.'], 422);
            }

            $embedding = $this->embeddingClient->embed($content);

            $document = KnowledgeBase::create([
                'software' => $request->validated('software'),
                'title' => $request->validated('title'),
                'content' => $content,
                'embedding' => $embedding,
            ]);

            return response()->json($document, 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Erro ao processar arquivo: ' . $e->getMessage()], 422);
        }
    }

    public function destroy(KnowledgeBase $knowledgeBase): JsonResponse
    {
        $knowledgeBase->delete();

        return response()->json(['message' => 'Documento excluido com sucesso.']);
    }
}

