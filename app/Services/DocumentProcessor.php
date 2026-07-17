<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;

class DocumentProcessor
{
    public function extractText($file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'txt' => $this->extractFromTxt($file->getRealPath()),
            'pdf' => $this->extractFromPdf($file->getRealPath()),
            'doc', 'docx' => $this->extractFromWord($file->getRealPath()),
            default => null,
        };
    }

    public function isValidContent(?string $content): bool
    {
        if (empty($content)) {
            return false;
        }

        $cleanContent = trim($content);
        return strlen($cleanContent) >= 20 && !$this->isCorrupted($cleanContent);
    }

    private function extractFromTxt(string $path): ?string
    {
        try {
            $content = file_get_contents($path);
            return is_string($content) ? trim($content) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractFromPdf(string $path): ?string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
            return !empty($text) ? trim($text) : null;
        } catch (\Throwable) {
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
                        $text = strip_tags($xml);
                        $text = preg_replace('/\s+/', ' ', trim($text));
                        return !empty($text) ? $text : null;
                    }
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isCorrupted(string $content): bool
    {
        $controlChars = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content);
        $invalidRatio = $controlChars / strlen($content);
        return $invalidRatio > 0.1;
    }
}

