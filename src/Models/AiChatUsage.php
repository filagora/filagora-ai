<?php

declare(strict_types=1);

namespace Filagora\Ai\Models;

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $user_id
 * @property string $provider
 * @property string $model
 * @property string|null $context
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property string|null $estimated_cost_usd
 * @property string $cost_basis
 * @property bool $succeeded
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
