<?php

namespace App\AI\Contracts;

interface EmbeddingServiceInterface
{
    /**
     * Generate an embedding vector for a single piece of text.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array;

    /**
     * Generate embedding vectors for multiple pieces of text.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts): array;
}
