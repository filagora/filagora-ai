<?php

declare(strict_types=1);

namespace Filagora\Ai\Filament\Resources\EmbeddingUsages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmbeddingUsageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $t = static fn (string $key): string => __("filagora-ai::messages.embedding_usages.table.{$key}");

        return $schema->components([
            Section::make(__('filagora-ai::messages.embedding_usages.infolist.call'))
                ->schema([
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('model'),
                    TextEntry::make('context')->placeholder('-'),
                    TextEntry::make('succeeded')
                        ->badge()
                        ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                        ->formatStateUsing(fn (bool $state): string => $state ? $t('succeeded') : $t('failed')),
                ])
                ->columns(2),
            Section::make(__('filagora-ai::messages.embedding_usages.infolist.usage'))
                ->schema([
                    TextEntry::make('input_tokens')->numeric()->placeholder('-'),
                    TextEntry::make('estimated_cost_usd')
                        ->label($t('estimated_cost'))
                        ->formatStateUsing(fn (?string $state): string => $state !== null ? '$'.number_format((float) $state, 6) : '-'),
                    TextEntry::make('created_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }
}
