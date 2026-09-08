<?php

namespace Tests;

use App\AI\Contracts\EmbeddingServiceInterface;
use App\AI\Contracts\LLMServiceInterface;
use App\AI\Fakes\FakeEmbeddingService;
use App\AI\Fakes\FakeLLMService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Rebind the AI provider interfaces to their fakes for every test, so no
     * automated test can ever call a real embedding/LLM API.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(EmbeddingServiceInterface::class, FakeEmbeddingService::class);
        $this->app->bind(LLMServiceInterface::class, FakeLLMService::class);
    }
}
