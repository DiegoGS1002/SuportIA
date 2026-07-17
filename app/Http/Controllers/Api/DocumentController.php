<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    /**
     * List all documents for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $documents = Document::query()
            ->when($request->user(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->get();

        return response()->json($documents);
    }

    /**
     * Download a document.
     */
    public function download(Document $document)
    {
        // Verificar permissão
        if ($document->user_id && $document->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->download(
            storage_path('app/public/' . $document->file_path),
            $document->name . '.pdf'
        );
    }

    /**
     * Delete a document.
     */
    public function destroy(Document $document): JsonResponse
    {
        // Verificar permissão
        if ($document->user_id && $document->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Deletar arquivo
        \Storage::disk('public')->delete($document->file_path);

        // Deletar registro
        $document->delete();

        return response()->json(['message' => 'Documento excluído com sucesso.']);
    }
}

