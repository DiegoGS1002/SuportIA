<?php

namespace App\AI\Tools;

use App\Services\Search\WebSearchService;

/**
 * Level 2 search tool: only used when the internal knowledge base (RAG)
 * does not have a satisfactory answer AND the user has explicitly
 * authorized an external search, as required by the operational prompt
 * (prompt/AgenteIA.md).
 */
final class WebSearchTool extends BaseTool
{
    public function __construct(
        private readonly WebSearchService $webSearchService,
    ) {}

    public function name(): string
    {
        return 'search_web';
    }

    public function description(): string
    {
        return 'Realiza uma pesquisa externa (via OpenAI) para tentar encontrar uma solucao quando a base interna nao possui a resposta. '.
            'So deve ser usada apos consentimento explicito do usuario.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Termo de busca objetivo, incluindo mensagem de erro, software (Home/Stella) e contexto.',
                ],
            ],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments): string
    {
        $query = (string) ($arguments['query'] ?? '');

        if (trim($query) === '') {
            return 'Nao foi possivel realizar a busca externa: consulta vazia.';
        }

        return $this->webSearchService->search($query);
    }
}


