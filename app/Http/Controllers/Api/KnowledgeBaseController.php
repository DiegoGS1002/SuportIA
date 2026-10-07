<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBaseStoreRequest;
use App\Jobs\ProcessKnowledgeBaseDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KnowledgeBaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->where('type', Document::TYPE_KNOWLEDGE_BASE)
            ->where('user_id', $request->user()->id)
            ->select([
                'id',
                'software',
                'title',
                'name',
                'file_size',
                'mime_type',
                'status',
                'error_message',
                'chunks_count',
                'created_at',
            ])
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

            $filePath = $uploadedFile->store('knowledge-base', 'local');

            $document = Document::create([
                'user_id' => $request->user()->id,
                'chat_id' => null,
                'type' => Document::TYPE_KNOWLEDGE_BASE,
                'name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $filePath,
                'disk' => 'local',
                'mime_type' => $uploadedFile->getMimeType(),
                'software' => $software,
                'title' => $title,
                'status' => 'uploaded',
                'file_size' => $uploadedFile->getSize(),
            ]);

            ProcessKnowledgeBaseDocument::dispatch($document->id);

            return response()->json([
                'message' => 'Documento recebido e enviado para processamento.',
                'document' => $document->fresh(),
            ], 202);
        } catch (\Throwable $e) {
            if (isset($document) && $document->exists()) {
                $document->delete();
            }
            if (isset($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            Log::error('Erro ao receber arquivo para processamento.', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Nao foi possivel receber o arquivo. Tente novamente.',
                'code' => 'document_upload_failed',
            ], 500);
        }
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        abort_unless(
            $document->type === Document::TYPE_KNOWLEDGE_BASE
                && $document->user_id === $request->user()->id,
            404,
        );

        Storage::disk($document->disk)->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Documento e indice excluidos com sucesso.']);
    }

    public function retry(Request $request, Document $document): JsonResponse
    {
        abort_unless(
            $document->type === Document::TYPE_KNOWLEDGE_BASE
                && $document->user_id === $request->user()->id,
            404,
        );

        abort_unless(in_array($document->status, ['failed', 'needs_ocr'], true), 409);

        $document->update(['status' => 'uploaded', 'error_message' => null]);
        ProcessKnowledgeBaseDocument::dispatch($document->id);

        return response()->json([
            'message' => 'Documento reenviado para processamento.',
            'document' => $document->fresh(),
        ], 202);
    }
}
