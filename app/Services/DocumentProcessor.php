<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;
use Illuminate\Support\Facades\Log; // Adicionado para logging
use DOMDocument;
use DOMXPath;

class DocumentProcessor
{
    private const int CHUNK_SIZE = 1000; // Tamanho máximo de caracteres por chunk
    private const int CHUNK_OVERLAP = 200; // Sobreposição entre chunks para manter contexto

    /**
     * Extracts text from a file given its path.
     *
     * @param string $path The absolute path to the file.
     * @return string|null The extracted text, or null if extraction fails or extension is not supported.
     */
    public function extractText(string $path): ?string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)); // Obter extensão do caminho

        return match ($extension) {
            'txt' => $this->extractFromTxt($path),
            'pdf' => $this->extractFromPdf($path),
            'doc', 'docx' => $this->extractFromWord($path),
            default => null,
        };
    }

    public function isValidContent(?string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        $cleanContent = trim($content);
        return mb_strlen($cleanContent) >= 10 && !$this->isCorrupted($cleanContent);
    }

    /**
     * Splits a given text into smaller chunks with optional overlap.
     *
     * @param string $text The text to chunk.
     * @return array<string> An array of text chunks.
     */
    public function chunkText(string $text): array
    {
        $chunks = [];
        $length = mb_strlen($text); // Usar mb_strlen para caracteres multi-byte
        $start = 0;

        while ($start < $length) {
            $chunk = mb_substr($text, $start, self::CHUNK_SIZE);
            $chunks[] = $chunk;
            $start += (self::CHUNK_SIZE - self::CHUNK_OVERLAP);
            if ($start < 0) { // Garante que 'start' não seja negativo
                $start = 0;
            }
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
            Log::info("PDF Parser: Texto extraído (tamanho: " . mb_strlen($trimmedText) . ", snippet: " . mb_substr($trimmedText, 0, 200) . "...) do arquivo: {$path}");

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
                    $xml = $zip->getFromName('word/document.xml');
                    $zip->close();

                    if ($xml !== false) {
                        $dom = new DOMDocument();
                        // Suppress warnings for malformed HTML
                        libxml_use_internal_errors(true);
                        $dom->loadXML($xml);
                        libxml_clear_errors();

                        $xpath = new DOMXPath($dom);
                        $nodes = $xpath->query('//w:t'); // Find all text nodes

                        $text = '';
                        foreach ($nodes as $node) {
                            $text .= $node->nodeValue;
                        }

                        $text = preg_replace('/\s+/', ' ', trim($text));
                        return !empty($text) ? $text : null;
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
