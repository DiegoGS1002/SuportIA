<?php

namespace App\Services\Search;

use OpenAI\Client;

/**
 * Level 2 search: simulates an external "web search" by delegating the
 * query to the OpenAI Chat Completions API with a research-oriented
 * system prompt. This is only invoked after explicit user consent
 * (see App\AI\Tools\WebSearchTool and App\AI\Agents\SupportAgent).
 */
final readonly class WebSearchService
{
    public function __construct(
        private Client $client,
        private string $model,
    ) {}

    public function search(string $query): string
    {
        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Voce e um pesquisador tecnico. Responda de forma objetiva, em Portugues, com passos '.
                        'praticos e verificaveis para resolver o problema descrito, como se estivesse pesquisando na internet. '.
                        'Se nao tiver certeza, deixe isso explicito.',
                ],
                [
                    'role' => 'user',
                    'content' => $query,
                ],
            ],
        ]);

        return $response->choices[0]->message->content
            ?? 'Nao foi possivel obter uma resposta da pesquisa externa no momento.';
    }
}

