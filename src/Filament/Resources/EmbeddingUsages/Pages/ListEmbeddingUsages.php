<?php

declare(strict_types=1);

namespace Filagora\Ai\Filament\Resources\EmbeddingUsages\Pages;

use Filagora\Ai\Filament\Resources\EmbeddingUsages\EmbeddingUsageResource;
use Filament\Resources\Pages\ListRecords;

class ListEmbeddingUsages extends ListRecords
{
    protected static string $resource = EmbeddingUsageResource::class;
}
