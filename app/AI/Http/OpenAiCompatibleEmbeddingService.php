<?php

namespace App\AI\Http;

use App\AI\Contracts\EmbeddingServiceInterface;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleEmbeddingService implements EmbeddingServiceInterface
{
    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0];
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedMany(array $texts): array
    {
        $response = Http::withToken(config('ai.api_key'))
            ->baseUrl(config('ai.base_url'))
            ->post('/embeddings', [
                'model' => config('ai.embedding_model'),
                'input' => $texts,
                'dimensions' => config('ai.embedding.dimensions'),
            ])
            ->throw()
            ->json();

        return array_map(
            fn (array $item): array => $item['embedding'],
            $response['data'],
        );
    }
}
