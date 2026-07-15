<?php

namespace App\AI\PromptTemplates;

/**
 * Centralizes the System Prompt used by the SupportAgent.
 *
 * The core operational rules live in prompt/AgenteIA.md (canonical prompt).
 * This class loads that file and enriches it with dynamic RAG context
 * before it is sent to the AI provider.
 */
final class SupportPrompt
{
    public function __construct(
        private readonly string $agentPromptPath,
    ) {}

    /**
     * Builds the final system prompt, injecting the RAG context
     * (Level 1 - internal knowledge base) when available.
     *
     * @param  array<int, array{title: string, content: string}>  $documents
     */
    public function build(?string $software = null, array $documents = []): string
    {
        $base = $this->loadBasePrompt();

        $sections = [$base];

        if ($software !== null) {
            $sections[] = "## Contexto Atual\nSoftware identificado nesta conversa: {$software}.";
        }

        if ($documents !== []) {
            $sections[] = $this->buildContextSection($documents);
        } else {
            $sections[] = "## Contexto Recuperado (RAG)\nNenhum documento interno relevante foi encontrado para esta mensagem.";
        }

        return implode("\n\n", $sections);
    }

    private function loadBasePrompt(): string
    {
        if (! is_file($this->agentPromptPath)) {
            return $this->fallbackPrompt();
        }

        $content = file_get_contents($this->agentPromptPath);

        return $content !== false ? $content : $this->fallbackPrompt();
    }

    private function fallbackPrompt(): string
    {
        return <<<'PROMPT'
            Voce e o assistente virtual de suporte tecnico da Data C, especialista nos softwares Home e Stella.
            Responda sempre em Portugues, seja objetivo e nao invente solucoes sem evidencia.
            PROMPT;
    }

    /**
     * @param  array<int, array{title: string, content: string}>  $documents
     */
    private function buildContextSection(array $documents): string
    {
        $items = array_map(
            static fn (array $document, int $index): string => sprintf(
                "%d. **%s**\n%s",
                $index + 1,
                $document['title'],
                $document['content'],
            ),
            $documents,
            array_keys($documents),
        );

        return "## Contexto Recuperado (RAG - Base Interna)\n".
            "Utilize as informacoes abaixo, se forem relevantes, para embasar sua resposta:\n\n".
            implode("\n\n", $items);
    }
}

