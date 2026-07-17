<?php

namespace App\Services\Search;

use App\AI\Embeddings\OpenAIEmbeddingClient;
use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * RAG Level 1: Retrieval Augmented Generation over the internal
 * knowledge base (guides, trainings, support history).
 *
 * Uses MySQL 9's native VECTOR_DISTANCE() function when available,
 * falling back to an in-PHP cosine similarity computation for other
 * database drivers (e.g. sqlite for local development/testing).
 */
final readonly class VectorSearchService
{
    public function __construct(
        private OpenAIEmbeddingClient $embeddingClient,
        private int $topK = 3,
        private float $maxDistance = 0.35,
    ) {}

    /**
     * Finds the most semantically similar documents for the given query.
     *
     * @return array<int, array{id: int, title: string, content: string, distance: float}>
     */
    public function search(string $query, ?string $software = null): array
    {
        $embedding = $this->embeddingClient->embed($query);

        $results = $this->supportsNativeVectorSearch()
            ? $this->searchWithMySqlVector($embedding, $software)
            : $this->searchWithPhpFallback($embedding, $software);

        return array_values(array_filter(
            $results,
            fn (array $result): bool => $result['distance'] <= $this->maxDistance && $this->isValidResult($result),
        ));
    }

    private function isValidResult(array $result): bool
    {
        return !empty(trim($result['content'])) && strlen(trim($result['content'])) > 10;
    }

    /**
     * MySQL 9 introduced the VECTOR type and VECTOR_DISTANCE()/VECTOR_FROM_TEXT()
     * functions. Older MySQL versions (or other drivers, e.g. sqlite) don't
     * support them, so we attempt the native query first and gracefully fall
     * back to a pure-PHP cosine similarity computation if it fails.
     */
    private function supportsNativeVectorSearch(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return Cache::remember('mysql_supports_vector_type', now()->addHour(), function (): bool {
            try {
                DB::selectOne("SELECT VECTOR_FROM_TEXT('[1,2,3]') AS v");

                return true;
            } catch (\Throwable) {
                return false;
            }
        });
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
            ->selectRaw('VECTOR_DISTANCE(embedding, VECTOR_FROM_TEXT(?), \'cosine\') AS distance', [$embeddingJson])
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
     * Pure-PHP cosine distance fallback, used when the database driver
     * does not support native vector operations (e.g. sqlite).
     *
     * @param  array<int, float>  $embedding
     * @return array<int, array{id: int, title: string, content: string, distance: float}>
     */
    private function searchWithPhpFallback(array $embedding, ?string $software): array
    {
        $documents = KnowledgeBase::query()
            ->whereNotNull('embedding')
            ->when($software !== null, fn ($query) => $query->where('software', $software))
            ->get(['id', 'title', 'content', 'embedding']);

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

