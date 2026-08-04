<?php
// app/Services/LiteLLMService.php
// Copy this to your Laravel project

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Exception;

class LiteLLMService
{
    private string $baseUrl;
    private string $virtualKey;
    private int $timeout;

    public function __construct(
        string $baseUrl = 'http://localhost:4000/v1',
        string $virtualKey = 'test',
        int $timeout = 120
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->virtualKey = $virtualKey;
        $this->timeout = $timeout;
    }

    /**
     * Chat completion - main method
     */
    public function chat(
        array $messages,
        string $model = 'deepseek-chat',
        array $options = []
    ): array {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 2000,
            'temperature' => 0.1,
            'stream' => false,
        ], $options);

        $response = $this->post('/chat/completions', $payload);
        return $this->handleResponse($response);
    }

    /**
     * Streaming chat completion
     */
    public function chatStream(
        array $messages,
        string $model = 'deepseek-chat',
        array $options = [],
        callable $onChunk
    ): void {
        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 2000,
            'temperature' => 0.1,
            'stream' => true,
        ], $options);

        $client = Http::withToken($this->virtualKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->post("{$this->baseUrl}/chat/completions", $payload);

        $body = $client->body();
        
        // Parse SSE stream
        $lines = explode("\n", $body);
        foreach ($lines as $line) {
            if (str_starts_with($line, 'data: ')) {
                $data = trim(substr($line, 6));
                if ($data === '[DONE]') break;
                
                $chunk = json_decode($data, true);
                if ($chunk && isset($chunk['choices'][0]['delta']['content'])) {
                    $onChunk($chunk['choices'][0]['delta']['content']);
                }
            }
        }
    }

    /**
     * Embeddings for RAG/semantic search
     */
    public function embeddings(array $input, string $model = 'text-embedding-3-small'): array
    {
        $response = $this->post('/embeddings', [
            'model' => $model,
            'input' => $input,
        ]);
        return $this->handleResponse($response);
    }

    /**
     * List available models
     */
    public function models(): array
    {
        $response = Http::withToken($this->virtualKey)
            ->timeout(30)
            ->get("{$this->baseUrl}/models");
        
        return $this->handleResponse($response);
    }

    /**
     * Health check
     */
    public function health(): bool
    {
        try {
            $response = Http::withToken($this->virtualKey)
                ->timeout(10)
                ->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get spend/cost for a virtual key (if using proxy with database)
     */
    public function getSpend(string $virtualKey = null): array
    {
        $key = $virtualKey ?? $this->virtualKey;
        $response = Http::withToken($key)
            ->timeout(30)
            ->get("{$this->baseUrl}/spend/logs");
        
        return $this->handleResponse($response);
    }

    /**
     * Internal: POST request
     */
    private function post(string $endpoint, array $payload): Response
    {
        return Http::withToken($this->virtualKey)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post("{$this->baseUrl}{$endpoint}", $payload);
    }

    /**
     * Internal: Handle response
     */
    private function handleResponse(Response $response): array
    {
        if ($response->failed()) {
            $error = $response->json();
            $message = $error['error']['message'] ?? $response->body();
            throw new Exception("LiteLLM API Error [{$response->status()}]: {$message}");
        }

        $data = $response->json();
        
        // Validate expected structure
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("Unexpected LiteLLM response structure: " . json_encode($data));
        }

        return $data;
    }
}

// Usage example in a Controller or Job:
//
// class AIController extends Controller
// {
//     public function summarizeRAB(Request $request, LiteLLMService $llm)
//     {
//         $messages = [
//             ['role' => 'system', 'content' => 'You are an ERP assistant. Summarize RAB data concisely.'],
//             ['role' => 'user', 'content' => "Summarize this RAB: " . $request->input('rab_data')],
//         ];
//
//         $result = $llm->chat($messages, 'deepseek-chat');
//         return response()->json([
//             'summary' => $result['choices'][0]['message']['content'],
//             'tokens' => $result['usage']['total_tokens'],
//             'cost' => $result['usage']['cost'] ?? 0,
//         ]);
//     }
// }

// Config in config/services.php:
// 'litellm' => [
//     'base_url' => env('LITELLM_BASE_URL', 'http://localhost:4000/v1'),
//     'virtual_key' => env('LITELLM_VIRTUAL_KEY', 'test'),
//     'timeout' => env('LITELLM_TIMEOUT', 120),
// ],
//
// Bind in AppServiceProvider:
// $this->app->singleton(LiteLLMService::class, function ($app) {
//     return new LiteLLMService(
//         config('services.litellm.base_url'),
//         config('services.litellm.virtual_key'),
//         config('services.litellm.timeout')
//     );
// });