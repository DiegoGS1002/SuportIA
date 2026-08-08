<?php

namespace App\Services\Search;

use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\DB;

/**
 * Persists embedding vectors for KnowledgeBase entries.
 *
 * MySQL's native VECTOR column stores a fixed-size binary representation,
 * so the JSON-encoded array produced by the model's `array` cast cannot be
 * inserted directly — it must be converted through STRING_TO_VECTOR(). When
 * native vector support isn't available (e.g. sqlite in local/testing), the
 * embedding is stored as plain JSON instead.
 */
final readonly class EmbeddingWriter
{
    public function __construct(
        private VectorTypeDetector $vectorTypeDetector,
    ) {}

    /**
     * @param  array<int, float>  $embedding
     */
    public function store(int $knowledgeBaseId, array $embedding): void
    {
        if ($this->vectorTypeDetector->supportsNativeType()) {
            DB::update(
                'UPDATE knowledge_bases SET embedding = STRING_TO_VECTOR(?) WHERE id = ?',
                [json_encode($embedding, JSON_THROW_ON_ERROR), $knowledgeBaseId]
            );

            return;
        }

        KnowledgeBase::where('id', $knowledgeBaseId)->update(['embedding' => $embedding]);
    }
}
