<?php

declare(strict_types=1);

use Filagora\Core\Schema\Adopter;
use Filagora\Core\Schema\AdoptionStatus;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Core\Schema\TableShape;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

const AI_MIGRATION = '2026_11_06_100000_create_filagora_ai_tables';

const AI_TABLES = ['ai_chat_usages', 'ai_chat_messages', 'ai_chat_conversations', 'ai_embedding_usages'];

function aiMigration(): object
{
    return require __DIR__.'/../../database/migrations/'.AI_MIGRATION.'.php';
}

function dropAiTables(): void
{
    Schema::disableForeignKeyConstraints();

    foreach (AI_TABLES as $table) {
        Schema::dropIfExists($table);
        Schema::dropIfExists('filagora_'.$table);
    }

    Schema::enableForeignKeyConstraints();
}

/*
 * These tests create and drop tables, which commits the transaction around the test, so each one
 * puts the prefixed tables back for the next.
 */
afterEach(function () {
    dropAiTables();
    config(['filagora.table_prefix' => 'filagora_']);
    aiMigration()->up();
});

it('ships a snapshot of the tables that matches the migration, column order included', function () {
    $snapshot = app(SchemaRegistry::class)->get('ai');

    expect($snapshot)->not->toBeNull()
        ->and(array_keys($snapshot->tables))->toBe(['ai_chat_conversations', 'ai_chat_messages', 'ai_chat_usages', 'ai_embedding_usages'])
        ->and($snapshot->migrationNames())->toBe([AI_MIGRATION]);

    foreach ($snapshot->tables as $base => $expected) {
        expect(TableShape::inspect($base)->differencesFrom($expected, compareColumnOrder: true))->toBe([], $base);
    }
});

it('keeps who a chat call was for, what for and how it was costed on the usage row', function () {
    $columns = array_keys(TableShape::inspect('ai_chat_usages')->columns);

    expect($columns)->toBe(['id', 'conversation_id', 'user_id', 'provider', 'model', 'context', 'input_tokens', 'output_tokens', 'estimated_cost_usd', 'cost_basis', 'succeeded', 'created_at', 'updated_at']);
});

it('adopts the live tables, which have no prefix, and records the migration', function () {
    dropAiTables();
    config(['filagora.table_prefix' => '']);
    aiMigration()->up();
    DB::table('migrations')->where('migration', AI_MIGRATION)->delete();

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('ai'));

    expect($plan->status)->toBe(AdoptionStatus::Adoptable, implode('; ', $plan->differences));

    $this->artisan('filagora:adopt', ['--package' => 'ai'])->assertExitCode(0);

    expect(DB::table('migrations')->where('migration', AI_MIGRATION)->count())->toBe(1);
});

it('refuses tables that lost an index', function () {
    dropAiTables();
    config(['filagora.table_prefix' => '']);
    aiMigration()->up();
    Schema::table('ai_chat_usages', fn ($table) => $table->dropIndex('ai_chat_usages_context_created_at_index'));

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('ai'));

    expect($plan->status)->toBe(AdoptionStatus::Mismatch)
        ->and($plan->differences)->toContain('ai_chat_usages: index ai_chat_usages_context_created_at_index is missing');
});
