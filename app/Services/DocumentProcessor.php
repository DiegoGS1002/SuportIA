<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentProcessor
{
    private const int CHUNK_SIZE = 1500;

    private const int CHUNK_OVERLAP = 250;

    private const int MAX_DOCX_UNCOMPRESSED_BYTES = 25 * 1024 * 1024;

    /**
     * Extracts text from a file given its path.
     *
     * @param  string  $path  The absolute path to the file.
     * @return string|null The extracted text, or null if extraction fails or extension is not supported.
     */
    public function extractText(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)); // Obter extensão do caminho

        return match ($extension) {
            'txt', 'md' => $this->extractFromTxt($path),
            'pdf' => $this->extractFromPdf($path),
            'docx' => $this->extractFromWord($path),
            default => null,
        };
    }

    public function isValidContent(?string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        $cleanContent = trim($content);

        return mb_strlen($cleanContent) >= 10 && ! $this->isCorrupted($cleanContent);
    }

    /**
     * Splits a given text into smaller chunks with optional overlap.
     *
     * @param  string  $text  The text to chunk.
     * @return array<string> An array of text chunks.
     */
    public function chunkText(string $text): array
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($text)) ?: [];
        $segments = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = preg_replace('/\s+/u', ' ', trim($paragraph)) ?? '';

            if ($paragraph === '') {
                continue;
            }

            $sentences = mb_strlen($paragraph) > self::CHUNK_SIZE
                ? (preg_split('/(?<=[.!?])\s+/u', $paragraph) ?: [$paragraph])
                : [$paragraph];

            foreach ($sentences as $sentence) {
                while (mb_strlen($sentence) > self::CHUNK_SIZE) {
                    $segments[] = mb_substr($sentence, 0, self::CHUNK_SIZE);
                    $sentence = mb_substr($sentence, self::CHUNK_SIZE);
                }

                if (trim($sentence) !== '') {
                    $segments[] = trim($sentence);
                }
            }
        }

        $chunks = [];
        $current = '';

        foreach ($segments as $segment) {
            $candidate = $current === '' ? $segment : $current."\n\n".$segment;

            if (mb_strlen($candidate) <= self::CHUNK_SIZE) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks[] = $current;
                $overlap = mb_substr($current, -self::CHUNK_OVERLAP);
                $candidate = $overlap."\n\n".$segment;
                $current = mb_strlen($candidate) <= self::CHUNK_SIZE ? $candidate : $segment;
            }
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    private function extractFromTxt(string $path): ?string
    {
        try {
            $content = file_get_contents($path);

            return is_string($content) ? trim($content) : null;
        } catch (\Throwable $e) {
            Log::error("Erro ao extrair texto de TXT: {$e->getMessage()}");

            return null;
        }
    }

    private function extractFromPdf(string $path): ?string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();

            if (empty($text)) {
                Log::warning("PDF Parser: Texto vazio extraído do arquivo: {$path}");

                return null;
            }

            $trimmedText = trim($text);
            Log::info('PDF Parser: texto extraido.', [
                'characters' => mb_strlen($trimmedText),
                'filename' => basename($path),
            ]);

            return $trimmedText;
        } catch (\Throwable $e) {
            Log::error("Erro ao extrair texto de PDF: {$e->getMessage()} no arquivo: {$path}");

            return null;
        }
    }

    private function extractFromWord(string $path): ?string
    {
        try {
            if (str_ends_with($path, '.docx')) {
                $zip = new \ZipArchive;

                if ($zip->open($path) === true) {
                    $totalUncompressedSize = 0;

                    for ($index = 0; $index < $zip->numFiles; $index++) {
                        $totalUncompressedSize += (int) ($zip->statIndex($index)['size'] ?? 0);

                        if ($totalUncompressedSize > self::MAX_DOCX_UNCOMPRESSED_BYTES) {
                            $zip->close();
                            Log::warning('DOCX excede o limite de descompactacao.', ['filename' => basename($path)]);

                            return null;
                        }
                    }

                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();

                    if ($xml !== false) {
                        $dom = new DOMDocument;
                        libxml_use_internal_errors(true);
                        $loaded = $dom->loadXML($xml, LIBXML_NONET);
                        libxml_clear_errors();

                        if (! $loaded) {
                            return null;
                        }

                        $xpath = new DOMXPath($dom);
                        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                        $paragraphNodes = $xpath->query('//w:p');

                        $paragraphs = [];
                        foreach ($paragraphNodes ?: [] as $paragraphNode) {
                            $nodes = $xpath->query('.//w:t', $paragraphNode);
                            $paragraph = '';

                            foreach ($nodes ?: [] as $node) {
                                $paragraph .= $node->nodeValue.' ';
                            }

                            if (trim($paragraph) !== '') {
                                $paragraphs[] = trim($paragraph);
                            }
                        }

                        $text = implode("\n\n", $paragraphs);

                        return $text !== '' ? $text : null;
                    }
                }
            }
            Log::warning("Word Parser: Não foi possível extrair texto de DOCX ou formato não suportado: {$path}");

            return null;
        } catch (\Throwable $e) {
            Log::error("Erro ao extrair texto de Word: {$e->getMessage()} no arquivo: {$path}");

            return null;
        }
    }

    private function isCorrupted(string $content): bool
    {
        // Ajustado para usar mb_strlen para consistência
        $controlChars = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content);
        $invalidRatio = $controlChars / mb_strlen($content);

        return $invalidRatio > 0.1;
    }
}
