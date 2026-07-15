<?php

namespace App\AI\Tools;

/**
 * Example future tool: checks SEFAZ status/codes for fiscal rejections.
 *
 * This is a stub implementation, ready to be wired to a real SEFAZ
 * status webservice/API when available.
 */
final class SefazCheckerTool extends BaseTool
{
    public function name(): string
    {
        return 'check_sefaz_status';
    }

    public function description(): string
    {
        return 'Consulta o status do servico SEFAZ e/ou o significado de um codigo de rejeicao fiscal.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uf' => [
                    'type' => 'string',
                    'description' => 'Sigla da UF (ex: SP, RJ, MG).',
                ],
                'rejection_code' => [
                    'type' => 'string',
                    'description' => 'Codigo de rejeicao retornado pela SEFAZ, se houver.',
                ],
            ],
            'required' => ['uf'],
            'additionalProperties' => false,
        ];
    }

    public function handle(array $arguments): string
    {
        $uf = (string) ($arguments['uf'] ?? 'N/A');
        $code = $arguments['rejection_code'] ?? null;

        return $code !== null
            ? "Consulta SEFAZ ({$uf}) para o codigo de rejeicao {$code} ainda nao esta disponivel nesta instalacao."
            : "Consulta de status SEFAZ ({$uf}) ainda nao esta disponivel nesta instalacao.";
    }
}

