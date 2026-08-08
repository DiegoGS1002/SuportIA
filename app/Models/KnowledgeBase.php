<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Importar BelongsTo

#[Fillable(['software', 'title', 'content', 'embedding', 'document_id'])] // Adicionado 'document_id'
class KnowledgeBase extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array', // Mantido o cast para array para MySQL 8.4 (JSON)
        ];
    }

    /**
     * Get the document that owns the knowledge base entry.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
