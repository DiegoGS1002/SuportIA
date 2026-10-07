<?php

namespace App\Services\Search;

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * RAG Level 1: Retrieval Augmented Generation over the internal
 * knowledge base (guides, trainings, support history).
 *
 * Uses MySQL's native VECTOR_DISTANCE() function when available (Enterprise/
 * HeatWave), falling back to an in-PHP cosine similarity computation for
 * other database drivers/editions (e.g. sqlite for local development/testing,
 * or MySQL Community Server which stores VECTOR columns but doesn't ship
 * VECTOR_DISTANCE()).
 */
final readonly class VectorSearchService
{
    public function __construct(
        private OpenAIEmbeddingClient $embeddingClient,
        private VectorTypeDetector $vectorTypeDetector,
        private int $topK = 3,
        private float $maxDistance = 0.6,
    ) {}

    /**
     * Finds the most semantically similar documents for the given query.
     *
     * @return array<int, array{id: int, title: string, content: string, distance: float}>
     */
    public function search(string $query, ?string $software = null): array
    {
        $embedding = $this->embeddingClient->embed($query);

        $results = $this->vectorTypeDetector->supportsNativeDistance()
            ? $this->searchWithMySqlVector($embedding, $software)
            : $this->searchWithPhpFallback($embedding, $software);

        return array_values(array_filter(
            $results,
            fn (array $result): bool => $result['distance'] <= $this->maxDistance && $this->isValidResult($result),
        ));
    }

    private function isValidResult(array $result): bool
    {
        return ! empty(trim($result['content'])) && strlen(trim($result['content'])) > 10;
    }

    /**
     * @param  array<int, float>  $embedding
     * @return array<int, array{id: int, title: string, content: string, distance: float}>
     */
    private function searchWithMySqlVector(array $embedding, ?string $software): array
    {
        $embeddingJson = json_encode($embedding, JSON_THROW_ON_ERROR);

        $query = DB::table('knowledge_bases')
            ->select('id', 'title', 'content')
            ->selectRaw('VECTOR_DISTANCE(embedding, STRING_TO_VECTOR(?), \'cosine\') AS distance', [$embeddingJson])
            ->whereNotNull('embedding');

        if ($software !== null) {
            $query->where('software', $software);
        }

        return $query
            ->orderBy('distance')
            ->limit($this->topK)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'title' => $row->title,
                'content' => $row->content,
                'distance' => (float) $row->distance,
            ])
            ->all();
    }

    /**
     * Pure-PHP cosine distance fallback, used when the database driver or
     * MySQL edition does not support VECTOR_DISTANCE(). When the column
     * uses the native VECTOR type, VECTOR_TO_STRING() converts it to a
     * JSON-compatible textual representation the model's `array` cast can
     * decode; otherwise the column already stores plain JSON.
     *
     * @param  array<int, float>  $embedding
     * @return array<int, array{id: int, title: string, content: string, distance: float}>
     */
    private function searchWithPhpFallback(array $embedding, ?string $software): array
    {
        $query = KnowledgeBase::query()
            ->whereNotNull('embedding')
            ->when($software !== null, fn ($query) => $query->where('software', $software));

        if ($this->vectorTypeDetector->supportsNativeType()) {
            $query->select(['id', 'title', 'content'])
                ->selectRaw('VECTOR_TO_STRING(embedding) AS embedding');
        } else {
            $query->select(['id', 'title', 'content', 'embedding']);
        }

        $documents = $query->get();

        return LazyCollection::make($documents)
            ->map(function (KnowledgeBase $document) use ($embedding) {
                $vector = $document->embedding ?? [];

                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'content' => $document->content,
                    'distance' => $vector === [] ? 1.0 : $this->cosineDistance($embedding, $vector),
                ];
            })
            ->sort(fn ($a, $b) => $a['distance'] <=> $b['distance'])
            ->take($this->topK)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineDistance(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 1.0;
        }

        $cosineSimilarity = $dot / (sqrt($normA) * sqrt($normB));

        return 1.0 - $cosineSimilarity;
    }
}
