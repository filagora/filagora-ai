<?php

declare(strict_types=1);

namespace Filagora\Ai\Tests\Unit;

use Filagora\Ai\Contracts\EmbeddingProvider;
use Filagora\Ai\Support\Embeddings;
use Filagora\Ai\Tests\Support\FakeEmbeddingProvider;
use Filagora\Ai\Tests\TestCase;

class EmbeddingsTest extends TestCase
{
    public function test_embeddings_are_off_unless_switched_on_and_then_have_no_length(): void
    {
        $this->assertFalse(Embeddings::enabled());
        $this->assertNull(Embeddings::dimensions());
    }

    public function test_switched_on_the_length_is_the_providers(): void
    {
        config(['filagora-ai.embeddings.enabled' => true]);
        $this->app->instance(EmbeddingProvider::class, new FakeEmbeddingProvider);

        $this->assertTrue(Embeddings::enabled());
        $this->assertSame(2, Embeddings::dimensions());
    }
}
