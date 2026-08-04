<?php
/**
 * Test script for RAB AI Copilot (Fase 1-3)
 * Run from project root: php _test_rab_ai.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Services\RabCopilotService;
use App\Services\AiAnalysisService;
use App\Models\CompanySetting;

echo "=== RAB AI Copilot Test Suite ===\n\n";

// Test 1: RabCopilotService - Pemasangan PJU
echo "1. RabCopilotService::generate('pemasangan_pju', 8)\n";
$svc = app(RabCopilotService::class);
$draft = $svc->generate('pemasangan_pju', 8);

echo "   Items generated: " . count($draft) . "\n";
$materialTotal = 0;
foreach ($draft as $i => $item) {
    $sub = $item['volume'] * $item['harga_satuan'];
    $materialTotal += $sub;
    echo "   $i. {$item['uraian_pekerjaan']} | Vol: {$item['volume']} {$item['satuan']} | Harga: " . number_format($item['harga_satuan'], 0, ',', '.') . " | Sub: " . number_format($sub, 0, ',', '.') . " | Sumber: {$item['sumber']}\n";
}
echo "   Total Material: Rp " . number_format($materialTotal, 0, ',', '.') . "\n";
echo "   Total + 30% Markup: Rp " . number_format($materialTotal * 1.3, 0, ',', '.') . "\n";
assert(count($draft) === 10, "Expected 10 items");
assert(abs($materialTotal - 109040000) < 1, "Expected 109,040,000");
echo "   ✅ PASSED (matches real RAB)\n\n";

// Test 2: RabCopilotService - Perawatan PJU (MC)
echo "2. RabCopilotService::generate('perawatan_pju', 12)\n";
$draft2 = $svc->generate('perawatan_pju', 12);
$materialTotal2 = 0;
foreach ($draft2 as $i => $item) {
    $materialTotal2 += $item['volume'] * $item['harga_satuan'];
}
echo "   Items: " . count($draft2) . " | Material: Rp " . number_format($materialTotal2, 0, ',', '.') . "\n";
assert(count($draft2) === 6, "Expected 6 items");
echo "   ✅ PASSED\n\n";

// Test 3: AiAnalysisService - getLlmConfig (OpenRouter)
echo "3. AiAnalysisService::getLlmConfig() with OpenRouter\n";
CompanySetting::set('llm_provider', 'openrouter', 'Llm Provider', 'ai');
CompanySetting::set('llm_model', 'deepseek/deepseek-chat', 'Llm Model', 'ai');

$aiSvc = app(AiAnalysisService::class);
$m = new ReflectionMethod($aiSvc, 'getLlmConfig');
$m->setAccessible(true);
$config = $m->invoke($aiSvc);

echo "   Provider: openrouter\n";
echo "   Model: {$config['model']}\n";
echo "   Base URL: {$config['base_url']}\n";
echo "   API Key: " . substr($config['api_key'] ?? 'NULL', 0, 15) . "...\n";
assert($config['model'] === 'deepseek/deepseek-chat');
assert($config['base_url'] === 'https://openrouter.ai/api/v1/chat/completions');
echo "   ✅ PASSED\n\n";

// Test 4: AiAnalysisService - getLlmConfig (DeepSeek)
echo "4. AiAnalysisService::getLlmConfig() with DeepSeek\n";
CompanySetting::set('llm_provider', 'deepseek', 'Llm Provider', 'ai');
$config = $m->invoke($aiSvc);
assert($config['model'] === 'deepseek-chat');
assert($config['base_url'] === 'https://api.deepseek.com/chat/completions');
echo "   ✅ PASSED\n\n";

// Test 5: AiAnalysisService - getLlmConfig (Custom)
echo "5. AiAnalysisService::getLlmConfig() with Custom\n";
CompanySetting::set('llm_provider', 'custom', 'Llm Provider', 'ai');
CompanySetting::set('custom_api_key', 'sk-CUSTOM-TEST', 'Custom Provider API Key', 'ai');
CompanySetting::set('custom_base_url', 'https://api.custom.example/v1/chat/completions', 'Custom Provider Base URL', 'ai');
CompanySetting::set('custom_model', 'custom-model-v1', 'Custom Provider Model', 'ai');
$config = $m->invoke($aiSvc);
assert($config['model'] === 'custom-model-v1');
assert($config['base_url'] === 'https://api.custom.example/v1/chat/completions');
assert($config['api_key'] === 'sk-CUSTOM-TEST');
echo "   ✅ PASSED\n\n";

// Test 6: CompanySetting encrypted keys
echo "6. CompanySetting encryption check\n";
$raw = \Illuminate\Support\Facades\DB::table('company_settings')
    ->where('key', 'openrouter_api_key')
    ->value('value');
echo "   openrouter_api_key RAW length: " . strlen($raw ?: '') . " (encrypted JSON)\n";
echo "   openrouter_api_key decrypted: " . CompanySetting::get('openrouter_api_key') . "\n";
assert(strlen($raw ?: '') > 100, "Key should be encrypted");
echo "   ✅ PASSED\n\n";

// Test 7: AI Price Dashboard data
echo "7. HargaReferensi count\n";
$refCount = \App\Models\HargaReferensi::count();
echo "   Total HargaReferensi rows: $refCount\n";
assert($refCount >= 36, "Expected at least 36 seeded rows");
echo "   ✅ PASSED\n\n";

echo "=== ALL TESTS PASSED ===\n";