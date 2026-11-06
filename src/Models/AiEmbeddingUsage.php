<?php

declare(strict_types=1);

namespace Filagora\Ai\Models;

use Filagora\Core\Support\Tables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property string|null $context
 * @property int|null $input_tokens
 * @property string|null $estimated_cost_usd
 * @property bool $succeeded
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiEmbeddingUsage extends Model
{
    public function getTable(): string
    {
        return Tables::name('ai_embedding_usages');
    }

    protected $fillable = [
        'provider',
        'model',
        'context',
        'input_tokens',
        'estimated_cost_usd',
        'succeeded',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:6',
            'succeeded' => 'boolean',
        ];
    }
}
