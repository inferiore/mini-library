<?php

namespace Tests\Feature;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\AI\Contracts\LLMServiceInterface;
use App\AI\Fakes\FakeEmbeddingService;
use App\AI\Fakes\FakeLLMService;
use Tests\TestCase;

/**
 * Throwaway smoke test for spec 001's AI provider abstraction (interfaces +
 * fakes, no real feature wired to them yet). Delete this once a later
 * RAG/AI spec (007/008) exercises these bindings for real via an actual
 * feature test.
 */
class AiBindingsSmokeTest extends TestCase
{
    public function test_embedding_service_resolves_to_the_fake_in_tests(): void
    {
        $service = $this->app->make(EmbeddingServiceInterface::class);

        $this->assertInstanceOf(FakeEmbeddingService::class, $service);
    }

    public function test_llm_service_resolves_to_the_fake_in_tests(): void
    {
        $service = $this->app->make(LLMServiceInterface::class);

        $this->assertInstanceOf(FakeLLMService::class, $service);
    }
}
