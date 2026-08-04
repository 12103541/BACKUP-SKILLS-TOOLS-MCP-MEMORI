<?php

// Test script for Multi-Provider LLM Configuration
// Run from project root: php _test_multi_provider_llm.php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CompanySetting;
use App\Services\AiAnalysisService;
use Illuminate\Support\Facades\Http;

echo "=== Multi-Provider LLM Config Test ===\n\n";

// 1. Test CompanySetting get/set with encryption
echo "1. CompanySetting Encryption Test\n";
$testKey = 'test_api_key_' . time();
$testValue = 'sk-test-' . bin2hex(random_bytes(16));

CompanySetting::set($testKey, $testValue, 'Test API Key', 'ai');
$retrieved = CompanySetting::get($testKey);
echo "  Set: $testValue\n";
echo "  Got: $retrieved\n";
echo "  Match: " . ($retrieved === $testValue ? '✅ PASS' : '❌ FAIL') . "\n\n";

// Check DB storage is encrypted
$raw = DB::table('company_settings')->where('key', $testKey)->value('value');
echo "  Raw DB (encrypted): " . substr($raw, 0, 30) . "...\n\n";

// Cleanup
CompanySetting::where('key', $testKey)->delete();

// 2. Test AiAnalysisService getLlmConfig()
echo "2. AiAnalysisService::getLlmConfig() Test\n";
$service = new AiAnalysisService();
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getLlmConfig');
$method->setAccessible(true);
$cfg = $method->invoke($service);

echo "  Provider: " . ($cfg['model'] ?? 'N/A') . "\n";
echo "  Model: " . ($cfg['model'] ?? 'N/A') . "\n";
echo "  Base URL: " . ($cfg['base_url'] ?? 'N/A') . "\n";
echo "  API Key: " . ($cfg['api_key'] ? substr($cfg['api_key'], 0, 10) . '...' : 'NOT SET') . "\n\n";

// 3. Test actual LLM call (if key is valid)
echo "3. LLM API Call Test\n";
if ($cfg['api_key'] && $cfg['base_url']) {
    try {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $cfg['api_key'],
        ])->post($cfg['base_url'], [
            'model' => $cfg['model'],
            'messages' => [['role' => 'user', 'content' => 'Reply with just "OK"']],
            'temperature' => 0.3,
        ]);
        
        if ($response->successful()) {
            echo "  Status: {$response->status()} ✅ PASS\n";
            echo "  Content: " . $response->json('choices.0.message.content') . "\n";
        } else {
            echo "  Status: {$response->status()} ❌ FAIL\n";
            echo "  Error: " . $response->body() . "\n";
        }
    } catch (Exception $e) {
        echo "  Exception: " . $e->getMessage() . " ❌ FAIL\n";
    }
} else {
    echo "  Skipped (no API key or base URL configured)\n";
}

echo "\n=== Test Complete ===\n";