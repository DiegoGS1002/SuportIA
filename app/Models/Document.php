<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'chat_id',
    'type',
    'name',
    'file_path',
    'disk',
    'mime_type',
    'software',
    'title',
    'status',
    'error_message',
    'chunks_count',
    'file_size',
])]
class Document extends Model
{
    public const string TYPE_KNOWLEDGE_BASE = 'knowledge_base';

    public const string TYPE_CHAT_EXPORT = 'chat_export';

    public const string TYPE_CHAT_IMAGE = 'chat_image';

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

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'attachment_document_id');
    }
}
