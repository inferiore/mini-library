<?php

namespace App\AI\Http;

use App\AI\Contracts\LLMServiceInterface;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleLLMService implements LLMServiceInterface
{
    public function generate(string $prompt): string
    {
        $response = Http::withToken(config('ai.api_key'))
            ->baseUrl(config('ai.base_url'))
            ->post('/chat/completions', [
                'model' => config('ai.chat_model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ])
            ->throw()
            ->json();

        return $response['choices'][0]['message']['content'];
    }
}
