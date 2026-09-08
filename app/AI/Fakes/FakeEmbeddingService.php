<?php

namespace App\AI\Fakes;

use App\AI\Contracts\EmbeddingServiceInterface;

class FakeEmbeddingService implements EmbeddingServiceInterface
{
    /**
     * Deterministic vector derived from a hash of the input string, so the
     * same input always produces the same vector without ever calling a
     * real embedding API.
     *
     * Uses a self-contained linear congruential generator (rather than
     * mt_srand/mt_rand) so it never perturbs PHP's global random state,
     * which other code running in the same process/test may depend on.
     *
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $dimensions = config('ai.embedding.dimensions');

        $state = crc32($text);
        $vector = [];

        for ($i = 0; $i < $dimensions; $i++) {
            $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
            $vector[] = ($state / 0x7FFFFFFF) * 2 - 1;
        }

        return $vector;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text): array => $this->embed($text), $texts);
    }
}
