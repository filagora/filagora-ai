<?php

declare(strict_types=1);

namespace Filagora\Ai\Filament\Resources\EmbeddingUsages;

use BackedEnum;
use Filagora\Ai\Filament\Resources\EmbeddingUsages\Pages\ListEmbeddingUsages;
use Filagora\Ai\Filament\Resources\EmbeddingUsages\Pages\ViewEmbeddingUsage;
use Filagora\Ai\Filament\Resources\EmbeddingUsages\Schemas\EmbeddingUsageInfolist;
use Filagora\Ai\Filament\Resources\EmbeddingUsages\Tables\EmbeddingUsagesTable;
use Filagora\Ai\Models\AiEmbeddingUsage;
use Filagora\Ai\Support\Abilities;
use Filagora\Core\Filament\Concerns\GatedByAbility;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Every embedding call the application has made, with its tokens and what they cost: the way to spot a
 * runaway index run or a provider that has started failing. Read-only, and only for those who may see
 * what the AI costs.
 */
class EmbeddingUsageResource extends Resource
{
    use GatedByAbility;

    protected static ?string $model = AiEmbeddingUsage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 40;

    /**
     * Read-only: only viewing is allowed, and only with the usage ability.
     */
    protected static function abilityFor(string|UnitEnum $action, ?Model $record): ?string
    {
        return in_array($action, ['viewAny', 'view'], true) ? Abilities::VIEW_USAGE : null;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('ai');
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-ai::messages.embedding_usages.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('filagora-ai::messages.embedding_usages.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filagora-ai::messages.embedding_usages.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmbeddingUsageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmbeddingUsagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmbeddingUsages::route('/'),
            'view' => ViewEmbeddingUsage::route('/{record}'),
        ];
    }
}
