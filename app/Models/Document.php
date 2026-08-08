<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // Importar HasMany

#[Fillable(['user_id', 'chat_id', 'name', 'file_path', 'file_size'])]
class Document extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Chat, $this>
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the knowledge base entries for the document.
     */
    public function knowledgeBases(): HasMany
    {
        return $this->hasMany(KnowledgeBase::class);
    }
}
