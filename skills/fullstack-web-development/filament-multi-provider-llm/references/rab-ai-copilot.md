# RAB AI Copilot — Local-First Generator + Multi-Provider LLM (2026-07-31)

## Overview
Three-phase RAB AI feature for ERP PT EXFERIA PUTRA INOVASI. **Fase 1 is 100% local (no API key required)**, Fase 2 seeds reference prices, Fase 3 adds optional LLM for narrative reports.

## Fase 1: Local Generator (`RabCopilotService.php`)

### Service Pattern
```php
class RabCopilotService {
    protected array $templates = [
        'pemasangan_pju' => [
            ['uraian' => 'Tiang PJU Octagonal 9m', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Lampu LED PJU 120W', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Kabel NYY 2x6mm', 'satuan' => 'm', 'per' => 'titik', 'volume_per' => 37.5],
            ['uraian' => 'MCB 1P 10A', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Panel PJU 1 Fasa', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Kontaktor 25A', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Timer Astronomis', 'satuan' => 'unit', 'per' => 'titik'],
            ['uraian' => 'Baut & Mur SS (set)', 'satuan' => 'set', 'per' => 'titik', 'volume_per' => 5],
            ['uraian' => 'Pekerjaan Instalasi & Setting', 'satuan' => 'ls', 'per' => 'ls'],
            ['uraian' => 'Mobilisasi & Pengiriman', 'satuan' => 'ls', 'per' => 'ls'],
        ],
        'perawatan_pju' => [ /* monthly items */ ],
    ];

    public function generate(string $jenis, float $volume): array {
        foreach ($this->templates[$jenis] ?? [] as $tpl) {
            $vol = match ($tpl['per']) {
                'titik', 'bulan' => round($volume * ($tpl['volume_per'] ?? 1), 2),
                'ls' => 1, 'fixed' => $tpl['volume'],
            };
            [$harga, $sumber] = $this->resolveHarga($tpl['uraian']);
            $items[] = ['pilih' => true, 'uraian_pekerjaan' => $tpl['uraian'], 'volume' => $vol,
                        'satuan' => $tpl['satuan'], 'harga_satuan' => $harga,
                        'jumlah_harga' => round($vol * $harga, 2), 'sumber' => $sumber];
        }
        return $items;
    }
}
```

### Price Resolution (Priority Order)
1. **Sparepart** — `Sparepart::where('nama_part', 'like', ...)->first()->harga_jual`
2. **HargaReferensi** — `HargaReferensi::where('nama_item', 'like', ...)->first()->harga_rata2`
3. **Riwayat RAB** — `RabKomponen::where('uraian_pekerjaan', 'like', ...)->latest()->first()->harga_satuan`
4. **Estimasi** — `0` (fallback)

### Verified Results (Fase 1)
| Scenario | Items | Material Total | +30% Markup |
|----------|-------|----------------|-------------|
| 8 titik PJU | 10 | Rp 109,040,000 | **Rp 141,752,000** |
| 12 bln MC | 6 | Rp 67,278,880 | Rp 87,462,544 |

## Fase 2: HargaReferensi Seeding
```bash
# Historical from RabKomponen (min/avg/max per item)
# Supplier from Sparepart.harga_jual
# Total: 36 rows (28 historis + 8 supplier)
```
- `cariHarga()` uses keyword matching with stopwords filter
- AI Price Dashboard: `/admin/rab/ai-price` — lists referensi, "Analisa RAB" dropdown calls `AiAnalysisService::analyzeRab()`
- 9/10 matched on test PJU RAB

## Fase 3: Multi-Provider LLM Integration
- `AiAnalysisService::getLlmConfig()` reads `llm_provider` + provider-specific keys
- UI in `CompanySettingPage`: Provider select + 4 API Key fields + custom provider fields
- Key fallback: `deepseek_api_key` → `DEEPSEEK_API_KEY`, `openrouter_api_key` → `OPENROUTER_API_KEY`, `custom_api_key` → `CUSTOM_API_KEY`, `gemini_api_key` → `GEMINI_API_KEY`/`LLM_API_KEY`
- OpenRouter key installed: `sk-899eeeff5f928c95-wjkqmj-9c0fd688` → model `deepseek/deepseek-chat`

## Filament UI Implementation

### CreateRab Header Action (Modal with Repeater)
```php
// In CreateRab.php
protected function getHeaderActions(): array {
    return [
        Action::make('ai_copilot')
            ->label('✨ Buat RAB dengan AI')
            ->form([                    // ← MUST use form(), NOT schema()
                Select::make('jenis')->options($this->rabCopilotService->jenisOptions())->required(),
                TextInput::make('volume')->numeric()->required()->default(8),
                Action::make('generate')
                    ->label('⚡ Generate Draft')
                    ->action(fn() => $this->generateDraft()),
                Repeater::make('draft_komponen')
                    ->schema([
                        Checkbox::make('pilih')->default(true)->label('Pilih'),
                        TextInput::make('uraian_pekerjaan')->required(),
                        TextInput::make('volume')->numeric()->required(),
                        Select::make('satuan')->options($satuanOptions)->required(),
                        TextInput::make('harga_satuan')->numeric()->required()
                            ->helperText('Harga dari: sparepart / referensi / riwayat / estimasi'),
                        TextInput::make('sumber')->disabled()->dehydrated(false),
                    ])
                    ->columns(6),
                Action::make('apply')
                    ->label('Terapkan ke RAB')
                    ->action(fn() => $this->applyDraft()),
            ])
            ->modalWidth('6xl'),
    ];
}

// Apply to main form
protected function applyDraft(): void {
    $selected = collect($this->draft_komponen)->where('pilih', true)->values()->toArray();
    $this->data['komponen'] = $selected;
    $this->form->fill(['komponen' => $selected]);
}
```

## Critical Pitfalls

| Issue | Fix |
|-------|-----|
| `Action::schema()` not exist | Use `->form([...])` for modal actions |
| Toggle in Repeater modal → entangle error | Use `Checkbox::make('pilih')->default(true)` |
| `formatStateUsing(number_format)` on numeric input breaks fill | Remove `formatStateUsing` from fields filled programmatically |
| `fill(['data' => $values])` vs flat statePath | Use `fill($values)` for flat state |
| Save expects nested `['data']` but state is flat | Use `$state['data'] ?? $state` |
| FileUpload expects array, DB stores string | Use `formatStateUsing(fn($v) => is_string($v) ? [$v] : ($v ?? []))` |
| Browser session timeout in headless | Test AI features in real browser |

## Testing Commands
```bash
# Test RabCopilotService
php artisan tinker --execute='
$svc = app(\App\Services\RabCopilotService::class);
$d = $svc->generate("pemasangan_pju", 8);
echo "Count: ".count($d)." Total: ".array_sum(array_column($d, "jumlah_harga"))."\n";
'

# Test AiAnalysisService config
php artisan tinker --execute='
$svc = app(\App\Services\AiAnalysisService::class);
$m = new ReflectionMethod($svc, "getLlmConfig"); $m->setAccessible(true);
echo json_encode($m->invoke($svc), JSON_PRETTY_PRINT);
'

# Check encrypted keys
php artisan tinker --execute='
echo \App\Models\CompanySetting::get("openrouter_api_key");
$raw = DB::table("company_settings")->where("key","openrouter_api_key")->value("value");
echo "RAW: ".substr($raw,0,20)."...";
'
```

## Files Modified/Created
- `app/Services/RabCopilotService.php` — NEW (118 lines)
- `app/Filament/Resources/RabResource/Pages/CreateRab.php` — MODIFIED (AI action + modal)
- `app/Filament/Resources/RabResource.php` — MODIFIED (removed formatStateUsing)
- `app/Services/AiAnalysisService.php` — GENERALIZED (multi-provider)
- `app/Models/CompanySetting.php` — SENSITIVE_KEYS + set() refactor
- `app/Filament/Pages/CompanySettingPage.php` — AI section with per-provider keys
- `app/Filament/Pages/Settings/ProfilPerusahaanPage.php` — HasForms + fix fill/save
- `app/Filament/Resources/RabResource/Pages/AiPriceDashboard.php` — NEW (36 referensi + analisa)