<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeBaseDocument;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * List all documents for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->id)
            ->where('type', Document::TYPE_CHAT_EXPORT)
            ->latest()
            ->get();

        return response()->json($documents);
    }

    /**
     * Download a document.
     */
    public function download(Request $request, Document $document)
    {
        abort_unless($document->user_id === $request->user()->id, 404);

        abort_unless(Storage::disk($document->disk)->exists($document->file_path), 404);

        return Storage::disk($document->disk)->download(
            $document->file_path,
            $document->name,
            ['Content-Type' => $document->mime_type ?? 'application/octet-stream'],
        );
    }

    /**
     * Re-index an exported conversation so it can be searched by the agent.
     */
    public function retry(Request $request, Document $document): JsonResponse
    {
        abort_unless(
            $document->user_id === $request->user()->id
                && $document->type === Document::TYPE_CHAT_EXPORT
                && $document->chat !== null,
            404,
        );

        $document->update([
            'software' => $document->chat->software,
            'title' => $document->title ?: pathinfo($document->name, PATHINFO_FILENAME),
            'status' => 'uploaded',
            'error_message' => null,
            'chunks_count' => 0,
        ]);

        ProcessKnowledgeBaseDocument::dispatch($document->id);

        return response()->json([
            'message' => 'Documento reenviado para indexacao.',
            'document' => $document->fresh(),
        ], 202);
    }

    /**
     * Delete a document.
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        abort_unless($document->user_id === $request->user()->id, 404);

        Storage::disk($document->disk)->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Documento excluído com sucesso.']);
    }
}
