<?php

declare(strict_types=1);

namespace Filagora\Ai\Tests\Feature;

use Filagora\Ai\Tests\TestCase;
use Filagora\Entitlements\EntitlementsServiceProvider;
use Filagora\Entitlements\Registry\EntitlementRegistry;

class EntitlementDeclarationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [EntitlementsServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    public function test_the_keys_the_abilities_map_to_are_declared_for_the_entitlement_sync(): void
    {
        $definitions = app(EntitlementRegistry::class)->definitions();

        $this->assertArrayHasKey('ai_chat.access', $definitions);
        $this->assertSame('AI Chat Access', $definitions['ai_chat.access']->label);
        $this->assertTrue($definitions['ai_chat.access']->wildcardChildren);
        $this->assertArrayHasKey('filagora.ai.view-usage', $definitions);
        $this->assertFalse($definitions['filagora.ai.view-usage']->wildcardChildren);
    }
}
