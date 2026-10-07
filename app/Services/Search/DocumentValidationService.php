<?php

namespace App\Services\Search;

use App\Models\KnowledgeBase;

class DocumentValidationService
{
    public static function validateStoredDocument(KnowledgeBase $document): bool
    {
        if (! $document->content || ! $document->embedding) {
            return false;
        }

        $content = trim($document->content);

        if (strlen($content) < 20) {
            return false;
        }

        if (! is_array($document->embedding) || empty($document->embedding)) {
            return false;
        }

        return true;
    }

    public static function getQualityScore(KnowledgeBase $document): float
    {
        $score = 1.0;

        $contentLength = strlen(trim($document->content ?? ''));
        if ($contentLength < 100) {
            $score -= 0.3;
        } elseif ($contentLength < 500) {
            $score -= 0.1;
        }

        if (! is_array($document->embedding) || empty($document->embedding)) {
            $score -= 0.5;
        }

        $wordCount = str_word_count($document->content ?? '');
        if ($wordCount < 5) {
            $score -= 0.3;
        }

        return max(0, $score);
    }

    public static function sanitizeContent(string $content): string
    {
        $content = preg_replace('/\s+/', ' ', trim($content));
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        return trim($content);
    }
}
