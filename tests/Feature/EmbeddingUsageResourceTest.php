<?php

declare(strict_types=1);

use Filagora\Ai\Filament\Resources\EmbeddingUsages\EmbeddingUsageResource;
use Filagora\Ai\Filament\Resources\EmbeddingUsages\Pages\ListEmbeddingUsages;
use Filagora\Ai\Filament\Resources\EmbeddingUsages\Pages\ViewEmbeddingUsage;
use Filagora\Ai\Models\AiEmbeddingUsage;
use Filagora\Ai\Tests\Support\HostAccess;
use Filagora\Ai\Tests\Support\TestUser;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    HostAccess::$granted = [];
    $this->call = fn (array $with = []): AiEmbeddingUsage => AiEmbeddingUsage::query()->create($with + [
        'provider' => 'openai', 'model' => 'text-embedding-3-small', 'context' => 'doc_page_index',
        'input_tokens' => 1200, 'estimated_cost_usd' => 0.000024, 'succeeded' => true,
    ]);
    $this->viewer = function (): TestUser {
        $user = TestUser::make('Staff');
        HostAccess::$granted[$user->id] = ['filagora.ai.view-usage'];

        return $user;
    };
});

it('is in the panel\'s AI group', function () {
    expect(EmbeddingUsageResource::getNavigationGroup())->toBe('AI')
        ->and(EmbeddingUsageResource::getNavigationLabel())->toBe('AI usage');
});

it('lets someone who may see what the AI costs list and open the calls', function () {
    $row = ($this->call)();
    actingAs(($this->viewer)());

    expect(EmbeddingUsageResource::canViewAny())->toBeTrue();

    Livewire::test(ListEmbeddingUsages::class)->assertOk()->assertCanSeeTableRecords([$row]);
    Livewire::test(ViewEmbeddingUsage::class, ['record' => $row->id])->assertOk();
});

it('keeps everyone else out', function () {
    $row = ($this->call)();
    actingAs(TestUser::make('Member'));

    expect(EmbeddingUsageResource::canViewAny())->toBeFalse()
        ->and(EmbeddingUsageResource::canView($row))->toBeFalse();
});

it('is read-only even for those who may look', function () {
    $row = ($this->call)();
    actingAs(($this->viewer)());

    expect(EmbeddingUsageResource::canCreate())->toBeFalse()
        ->and(EmbeddingUsageResource::canEdit($row))->toBeFalse()
        ->and(EmbeddingUsageResource::canDelete($row))->toBeFalse();
});

it('lists the newest call first', function () {
    $old = ($this->call)();
    $old->forceFill(['created_at' => now()->subDay()])->save();
    $new = ($this->call)();
    actingAs(($this->viewer)());

    Livewire::test(ListEmbeddingUsages::class)->assertCanSeeTableRecords([$new, $old], inOrder: true);
});

it('filters by the providers and contexts that have been used, whatever they are called', function () {
    $mine = ($this->call)(['provider' => 'custom-host', 'context' => 'thread_vector_index']);
    $other = ($this->call)();
    actingAs(($this->viewer)());

    Livewire::test(ListEmbeddingUsages::class)
        ->filterTable('provider', 'custom-host')
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other])
        ->resetTableFilters()
        ->filterTable('context', 'doc_page_index')
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$mine]);
});

it('filters failed calls', function () {
    $ok = ($this->call)();
    $failed = ($this->call)(['succeeded' => false, 'input_tokens' => null, 'estimated_cost_usd' => null]);
    actingAs(($this->viewer)());

    Livewire::test(ListEmbeddingUsages::class)
        ->filterTable('succeeded', false)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$ok]);
});
