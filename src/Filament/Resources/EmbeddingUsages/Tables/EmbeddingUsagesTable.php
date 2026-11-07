<?php

declare(strict_types=1);

namespace Filagora\Ai\Filament\Resources\EmbeddingUsages\Tables;

use Filagora\Ai\Models\AiEmbeddingUsage;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EmbeddingUsagesTable
{
    public static function configure(Table $table): Table
    {
        $t = static fn (string $key): string => __("filagora-ai::messages.embedding_usages.table.{$key}");
        $money = static fn (?string $state): string => $state !== null ? '$'.number_format((float) $state, 6) : '-';

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('provider')->badge()->sortable(),
                TextColumn::make('model')->toggleable(),
                TextColumn::make('context')->placeholder('-')->sortable(),
                TextColumn::make('input_tokens')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable()
                    ->summarize(Sum::make()->label($t('total_tokens'))->numeric()),
                TextColumn::make('estimated_cost_usd')
                    ->label($t('estimated_cost'))
                    ->formatStateUsing($money)
                    ->sortable()
                    ->summarize(Sum::make()->label($t('total_estimated_cost'))->formatStateUsing($money)),
                TextColumn::make('succeeded')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? $t('succeeded') : $t('failed'))
                    ->sortable(),
            ])
            ->filters([
                // The providers and contexts that have actually been used, since a host adds its own.
                SelectFilter::make('provider')->options(fn (): array => self::distinct('provider')),
                SelectFilter::make('context')->options(fn (): array => self::distinct('context')),
                TernaryFilter::make('succeeded')->label($t('result')),
            ])
            ->recordActions([ViewAction::make()])
            ->recordActionsPosition(RecordActionsPosition::BeforeCells)
            ->toolbarActions([]);
    }

    /**
     * @return array<string, string>
     */
    private static function distinct(string $column): array
    {
        return AiEmbeddingUsage::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column, $column)->all();
    }
}
