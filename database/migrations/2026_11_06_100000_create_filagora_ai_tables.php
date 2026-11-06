<?php

declare(strict_types=1);

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Chat conversations and messages, and a record of every chat and embedding call. Key and
 * foreign key names are written out, unprefixed, as sites that already have these tables
 * have them. `ai_chat_usages` is in its attributed shape: who the call was for, what for,
 * and how its cost was arrived at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(Tables::name('ai_chat_conversations'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title', 120)->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('model', 60)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at'], 'ai_chat_conversations_user_id_updated_at_index');
            $table->foreign('user_id', 'ai_chat_conversations_user_id_foreign')
                ->references('id')->on(UserModel::table())->cascadeOnDelete();
        });

        Schema::create(Tables::name('ai_chat_messages'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('role', 16);
            $table->longText('content');
            $table->json('retrieved_context')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'ai_chat_messages_conversation_id_created_at_index');
            $table->foreign('conversation_id', 'ai_chat_messages_conversation_id_foreign')
                ->references('id')->on(Tables::name('ai_chat_conversations'))->cascadeOnDelete();
        });

        Schema::create(Tables::name('ai_chat_usages'), function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            // Null for a system call, or when the person has since been deleted: the spend still happened.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('provider', 20);
            $table->string('model', 60);
            $table->string('context', 40)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            // reported, fallback_price (no price configured), estimated_tokens (cut off) or none.
            $table->string('cost_basis', 20)->default('reported');
            $table->boolean('succeeded');
            $table->timestamps();

            $table->index('conversation_id', 'ai_chat_usages_conversation_id_foreign');
            $table->index(['provider', 'created_at'], 'ai_chat_usages_provider_created_at_index');
            $table->index('user_id', 'ai_chat_usages_user_id_foreign');
            $table->index(['user_id', 'created_at'], 'ai_chat_usages_user_id_created_at_index');
            $table->index(['context', 'created_at'], 'ai_chat_usages_context_created_at_index');

            $table->foreign('conversation_id', 'ai_chat_usages_conversation_id_foreign')
                ->references('id')->on(Tables::name('ai_chat_conversations'))->nullOnDelete();
            $table->foreign('user_id', 'ai_chat_usages_user_id_foreign')
                ->references('id')->on(UserModel::table())->nullOnDelete();
        });

        Schema::create(Tables::name('ai_embedding_usages'), function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 20);
            $table->string('model', 60);
            $table->string('context', 60)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            $table->boolean('succeeded');
            $table->timestamps();

            $table->index(['provider', 'created_at'], 'ai_embedding_usages_provider_created_at_index');
            $table->index('context', 'ai_embedding_usages_context_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(Tables::name('ai_embedding_usages'));
        Schema::dropIfExists(Tables::name('ai_chat_usages'));
        Schema::dropIfExists(Tables::name('ai_chat_messages'));
        Schema::dropIfExists(Tables::name('ai_chat_conversations'));
    }
};
