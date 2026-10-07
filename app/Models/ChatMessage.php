<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_id', 'role', 'content', 'status', 'idempotency_key', 'attachment_document_id', 'metadata'])]
class ChatMessage extends Model
{
    public const string ROLE_USER = 'user';

    public const string ROLE_ASSISTANT = 'assistant';

    public const string ROLE_TOOL = 'tool';

    public const string ROLE_SYSTEM = 'system';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Chat, $this>
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'attachment_document_id');
    }
}
