<?php

declare(strict_types=1);

namespace Filagora\Ai\Models;

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string|null $provider
 * @property string|null $model
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiChatConversation extends Model
{
    public function getTable(): string
    {
        return Tables::name('ai_chat_conversations');
    }

    protected $fillable = [
        'user_id',
        'title',
        'provider',
        'model',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class());
    }

    /**
     * @return HasMany<AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'conversation_id')->orderBy('created_at');
    }
}
