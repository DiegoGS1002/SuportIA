<?php

namespace App\Services\Search;

use OpenAI\Client;

/**
 * Level 2 search backed by OpenAI's web-search Chat Completions model.
 */
final readonly class WebSearchService
{
    public function __construct(
        private Client $client,
        private string $model,
    ) {}

    public function search(string $query): WebSearchResult
    {
        $response = $this->client->chat()->create([
            'model' => $this->model,
            'web_search_options' => [
                'search_context_size' => 'medium',
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Pesquise fontes tecnicas atuais e responda em Portugues com passos praticos e verificaveis. '.
                        'Diferencie fatos encontrados de inferencias e preserve as citacoes retornadas pela pesquisa.',
                ],
                [
                    'role' => 'user',
                    'content' => $query,
                ],
            ],
        ]);

        $message = $response->choices[0]->message;
        $sources = [];

        foreach ($message->annotations as $annotation) {
            $citation = $annotation->urlCitations;
            $sources[$citation->url] = [
                'title' => $citation->title,
                'url' => $citation->url,
            ];
        }

        return new WebSearchResult(
            content: $message->content ?? 'Nao foi possivel obter uma resposta da pesquisa externa no momento.',
            sources: array_values($sources),
        );
    }
}
