<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configures the OpenAI-compatible HTTP client used for both chat/LLM and
    | embedding calls. Any provider that exposes an OpenAI-compatible API
    | (Gemini's OpenAI-compatible endpoint, OpenAI itself, etc.) can be used
    | by changing these values only — no code changes required.
    |
    */

    'base_url' => env('LLM_BASE_URL'),

    'api_key' => env('LLM_API_KEY'),

    'chat_model' => env('CHAT_MODEL'),

    'embedding_model' => env('EMBEDDING_MODEL'),

    'embedding' => [
        'dimensions' => (int) env('VECTOR_DIM', 1536),
    ],

];
