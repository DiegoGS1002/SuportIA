<?php

namespace App\AI\Embeddings;

use OpenAI\Client;
use RuntimeException;

/**
 * Thin wrapper around the OpenAI embeddings endpoint.
 *
 * Generates vector representations (text-embedding-3-*) used by the
 * VectorSearchService to perform RAG similarity search.
 */
final readonly class OpenAIEmbeddingClient
{
    public function __construct(
        private Client $client,
        private string $model,
        private int $dimensions,
    ) {}

    /**
     * Create the embedding vector for a single text input.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Cannot generate an embedding for an empty text.');
        }

        $response = $this->client->embeddings()->create([
            'model' => $this->model,
            'input' => $text,
            'dimensions' => $this->dimensions,
        ]);

        return $response->embeddings[0]->embedding;
    }

    /**
     * Create embeddings for multiple text inputs in a single request.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts): array
    {
        $texts = array_values(array_filter(array_map('trim', $texts), fn (string $text) => $text !== ''));

        if ($texts === []) {
            return [];
        }

        $response = $this->client->embeddings()->create([
            'model' => $this->model,
            'input' => $texts,
            'dimensions' => $this->dimensions,
        ]);

        return array_map(fn ($item) => $item->embedding, $response->embeddings);
    }
}
