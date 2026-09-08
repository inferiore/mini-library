<?php

namespace App\AI\Contracts;

interface LLMServiceInterface
{
    /**
     * Generate a text completion for the given prompt.
     */
    public function generate(string $prompt): string;
}
