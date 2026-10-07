<?php

namespace App\Services\Vision;

use Illuminate\Http\UploadedFile;
use OpenAI\Client;
use Symfony\Component\Process\Process;

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

    public function extractTextFromPdf(string $pdfPath): string
    {
        if (! is_file($pdfPath)) {
            return '';
        }

        $temporaryDirectory = sys_get_temp_dir().'/suportia-ocr-'.bin2hex(random_bytes(8));

        if (! mkdir($temporaryDirectory, 0700, true) && ! is_dir($temporaryDirectory)) {
            return '';
        }

        try {
            $outputPrefix = $temporaryDirectory.'/page';
            $process = new Process([
                'pdftoppm',
                '-f', '1',
                '-l', '10',
                '-r', '150',
                '-png',
                $pdfPath,
                $outputPrefix,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                return '';
            }

            $texts = [];
            foreach (glob($outputPrefix.'-*.png') ?: [] as $imagePath) {
                $uploadedFile = new UploadedFile(
                    $imagePath,
                    basename($imagePath),
                    'image/png',
                    null,
                    true,
                );
                $text = $this->extractText($uploadedFile);

                if ($text !== '') {
                    $texts[] = $text;
                }
            }

            return implode("\n\n", $texts);
        } finally {
            foreach (glob($temporaryDirectory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($temporaryDirectory);
        }
    }
}
