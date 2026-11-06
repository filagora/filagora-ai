<?php

declare(strict_types=1);

namespace Filagora\Ai\Models;

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatUsage extends Model
{
    public function getTable(): string
    {
        return Tables::name('ai_chat_usages');
    }

    protected $fillable = [
        'conversation_id',
        'user_id',
        'provider',
        'model',
        'context',
        'input_tokens',
        'output_tokens',
        'estimated_cost_usd',
        'cost_basis',
        'succeeded',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'succeeded' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class());
    }

    /**
     * @return BelongsTo<AiChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }
}
