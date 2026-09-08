<?php

namespace App\AI\Fakes;

use App\AI\Contracts\LLMServiceInterface;

class FakeLLMService implements LLMServiceInterface
{
    /**
     * Deterministic templated output so tests can assert on it without ever
     * calling a real LLM API.
     *
     * When the prompt is a grounded recommendation prompt (spec 008 —
     * App\AI\Prompts\RecommendationPrompt lists each candidate as
     * `Book ID N | Title: "..."`), the fake mimics a correctly-behaving model:
     * it returns strict JSON recommending only the Book IDs actually present in
     * the prompt, with a title-grounded reason per book. This lets the
     * recommendation parser and tests assert on a known, hallucination-free
     * shape without a real LLM.
     */
    public function generate(string $prompt): string
    {
        if (preg_match_all('/Book ID (\d+) \| Title: "([^"]*)"/', $prompt, $matches, PREG_SET_ORDER) > 0) {
            $recommendations = array_map(fn (array $match): array => [
                'book_id' => (int) $match[1],
                'reason' => "\"{$match[2]}\" closely matches what you described.",
            ], $matches);

            return (string) json_encode(['recommendations' => $recommendations]);
        }

        return "Fake response to: {$prompt}";
    }
}
