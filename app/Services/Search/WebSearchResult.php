<?php

namespace App\Services\Search;

final readonly class WebSearchResult
{
    /**
     * @param  array<int, array{title: string, url: string}>  $sources
     */
    public function __construct(
        public string $content,
        public array $sources = [],
    ) {}
}
