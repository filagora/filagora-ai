<?php

declare(strict_types=1);

namespace Filagora\Ai\Models;

use Filagora\Ai\Enums\AiChatMessageRole;
use Filagora\Core\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversation_id
 * @property AiChatMessageRole $role
 * @property string $content
 * @property list<array<string, mixed>>|null $retrieved_context
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiChatMessage extends Model
{
    public function getTable(): string
    {
        return Tables::name('ai_chat_messages');
    }

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'retrieved_context',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiChatMessageRole::class,
            'retrieved_context' => 'array',
        ];
    }

    /**
     * @return BelongsTo<AiChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }
}
