<?php

declare(strict_types=1);

namespace Filagora\Ai;

use Filagora\Ai\Console\AiUsageReportCommand;
use Filagora\Ai\Contracts\ChatProvider;
use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Models\AiChatConversation;
use Filagora\Ai\Policies\AiChatConversationPolicy;
use Filagora\Ai\Retrieval\RetrievalSources;
use Filagora\Ai\Services\ChatProviderManager;
use Filagora\Ai\Services\ChatUsageRecorder;
use Filagora\Ai\Services\EmbeddingProviderManager;
use Filagora\Ai\Services\EmbeddingUsageRecorder;
use Filagora\Ai\Services\GroundingPrompt;
use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Core\Support\GateAbilities;
use Filagora\Entitlements\Registry\Definition;
use Filagora\Entitlements\Registry\EntitlementRegistry;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-ai.php', 'filagora-ai');

        $this->app->singleton(ChatProviderManager::class);
        $this->app->singleton(EmbeddingProviderManager::class);
        $this->app->singleton(RetrievalSources::class);
        $this->app->singleton(GroundingPrompt::class);
        $this->app->singleton(ChatUsageRecorder::class);
        $this->app->singleton(EmbeddingUsageRecorder::class);
        $this->app->bind(ChatProvider::class, fn ($app): ChatProvider => $app->make(ChatProviderManager::class)->resolve());
        $this->app->bind(EmbeddingProvider::class, fn ($app): EmbeddingProvider => $app->make(EmbeddingProviderManager::class)->resolve());
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('ai', 'Filagora AI', 'filagora/filagora-ai', requires: ['core']),
        );

        $this->app->make(SchemaRegistry::class)->register(
            PackageSchema::fromFile('ai', __DIR__.'/../database/schema.php', __DIR__.'/../database/migrations'),
        );

        GateAbilities::define('filagora-ai.gates');
        Gate::policy(AiChatConversation::class, AiChatConversationPolicy::class);
        $this->declareEntitlements();

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filagora-ai');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([AiUsageReportCommand::class]);

            $this->publishes([
                __DIR__.'/../config/filagora-ai.php' => config_path('filagora-ai.php'),
            ], 'filagora-ai-config');
        }
    }

    /**
     * With `filagora-entitlements` installed, the keys the abilities map to are declared so
     * `filagora:entitlements:sync` creates their definitions. Without it there is nothing to declare them to.
     */
    private function declareEntitlements(): void
    {
        if (! class_exists(EntitlementRegistry::class) || ! $this->app->bound(EntitlementRegistry::class)) {
            return;
        }

        $registry = $this->app->make(EntitlementRegistry::class);

        foreach (GateAbilities::keys('filagora-ai.gates') as $key) {
            $registry->define($key === 'ai_chat.access'
                ? new Definition($key, 'AI Chat Access', 'Allows a user to access the AI-assisted chat system.')
                : new Definition($key, wildcardChildren: false));
        }
    }
}
