<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class LiteLlmService
{
    protected string $baseUrl;
    protected string $masterKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::get('litellm.base_url', 'http://localhost:4000/v1'), '/');
        $this->masterKey = Config::get('litellm.master_key', 'sk-litellm-master-2024');
        $this->timeout = Config::get('litellm.timeout', 60);
    }

    /**
     * Call LiteLLM proxy for chat completion
     */
    public function chat(array $messages, array $options = []): ?string
    {
        $model = $options['model'] ?? Config::get('litellm.default_model', 'deepseek-chat');
        $maxTokens = $options['max_tokens'] ?? 2000;
        $temperature = $options['temperature'] ?? 0.3;
        $systemPrompt = $options['system_prompt'] ?? null;

        $payload = [
            'model' => $model,
            'messages' => $this->formatMessages($messages, $systemPrompt),
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'stream' => false,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->masterKey}",
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout)
              ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::error('LiteLLM API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'model' => $model,
            ]);
        } catch (\Exception $e) {
            Log::error('LiteLLM Service Exception', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);
        }

        // Fallback to configured fallback model
        $fallbackModel = Config::get('litellm.fallback_model');
        if ($fallbackModel && $model !== $fallbackModel) {
            Log::info("LiteLLM: Falling back to {$fallbackModel}");
            return $this->chat($messages, array_merge($options, ['model' => $fallbackModel]));
        }

        return null;
    }

    /**
     * Simple chat with single user message
     */
    public function ask(string $prompt, array $options = []): ?string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }

    /**
     * Structured analysis with system prompt
     */
    public function analyze(string $data, string $systemPrompt, array $options = []): ?string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $data],
        ], array_merge($options, ['system_prompt' => $systemPrompt]));
    }

    /**
     * Format messages with optional system prompt
     */
    protected function formatMessages(array $messages, ?string $systemPrompt = null): array
    {
        if ($systemPrompt) {
            array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
        }
        return $messages;
    }

    /**
     * Check if service is available
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->masterKey}",
            ])->timeout(5)
              ->get("{$this->baseUrl}/models");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available models from proxy
     */
    public function getModels(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->masterKey}",
            ])->timeout(10)
              ->get("{$this->baseUrl}/models");
            if ($response->successful()) {
                return $response->json('data', []);
            }
        } catch (\Exception $e) {
            Log::error('LiteLLM Get Models Failed', ['error' => $e->getMessage()]);
        }
        return [];
    }
}