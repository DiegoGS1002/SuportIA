<?php

namespace App\Services\Vision;

use Illuminate\Http\UploadedFile;
use OpenAI\Client;

/**
 * Extracts relevant text from an uploaded screenshot/print using the
 * OpenAI vision-capable chat model, so it can be used as the primary
 * search key for the support flow (see prompt/AgenteIA.md - OCR section).
 */
final readonly class OCRService
{
    public function __construct(
        private Client $client,
        private string $model,
    ) {}

    public function extractText(UploadedFile $image): string
    {
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mime = $image->getMimeType() ?? 'image/png';

        $response = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Extraia todo o texto relevante desta imagem (mensagens de erro, codigos, telas de sistema). '.
                        'Responda apenas com o texto extraido, sem comentarios adicionais.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Extraia o texto desta imagem de suporte tecnico:',
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:{$mime};base64,{$base64}",
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return trim($response->choices[0]->message->content ?? '');
    }
}

