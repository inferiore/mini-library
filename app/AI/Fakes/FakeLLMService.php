<?php

namespace App\AI\Fakes;

use App\AI\Contracts\LLMServiceInterface;

class FakeLLMService implements LLMServiceInterface
{
    /**
     * Deterministic templated output so tests can assert on it without ever
     * calling a real LLM API.
     */
    public function generate(string $prompt): string
    {
        return "Fake response to: {$prompt}";
    }
}
