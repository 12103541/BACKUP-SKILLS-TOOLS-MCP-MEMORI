<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LiteLLM Proxy Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the LiteLLM AI Gateway proxy server.
    | The proxy provides unified access to 100+ LLM providers via OpenAI format.
    |
    */

    'base_url' => env('LITELLM_BASE_URL', 'http://localhost:4000/v1'),

    'master_key' => env('LITELLM_MASTER_KEY', 'sk-litellm-master-2024'),

    'timeout' => env('LITELLM_TIMEOUT', 60),

    'default_model' => env('LITELLM_DEFAULT_MODEL', 'deepseek-chat'),

    'fallback_model' => env('LITELLM_FALLBACK_MODEL', 'gpt-4o-mini'),

    'models' => [
        'deepseek-chat' => [
            'name' => 'DeepSeek Chat',
            'provider' => 'OpenRouter',
            'max_tokens' => 4096,
        ],
        'gpt-4o-mini' => [
            'name' => 'GPT-4o Mini',
            'provider' => 'OpenAI via OpenRouter',
            'max_tokens' => 4096,
        ],
    ],
];