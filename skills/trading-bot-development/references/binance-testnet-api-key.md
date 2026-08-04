# Binance Testnet API Key — Reproduction Steps

Self-contained walkthrough to recover when the user gets stuck finding or regenerating their Binance Testnet API credentials.

## URL
- **Testnet**: https://testnet.binance.vision/
- Note: visually resembles live Binance. If user has both tabs open, they're easily confused.

## What the user sees after login
- Dashboard with "Balances: 10000 USDT (test money)"
- Button: **Generate HMAC SHA-256 key** (sometimes labeled "Create API Key")
- After generation:
  - `API Key`: ~64-char string (always shown)
  - `Secret Key`: shown ONCE at generation. After page refresh: hidden behind "Show Secret" toggle. After closing modal: **irrecoverable** — must regenerate.

## Happy-path steps
1. Log in (GitHub OAuth is the only option on testnet)
2. On dashboard, click **Generate HMAC SHA-256 key**
3. Modal appears with both keys — **copy both IMMEDIATELY**
4. Paste into `trading-bot/.env`:
   - `EXCHANGE_API_KEY=<paste key>`
   - `EXCHANGE_API_SECRET=<paste secret>`
   - `EXCHANGE_TESTNET=true`
5. Save file. Test: `python main.py run --mode=live --once`

## Stuck-state troubleshooting

| User says... | Likely cause | Fix |
|--------------|--------------|-----|
| "I can't find the API key" | Generated before scrolling, key is below the fold OR on a different dashboard | Re-load dashboard, scroll fully |
| "Secret is blank/gone" | Secret was shown once and modal was closed | Click Generate again — old secret is GONE forever |
| "I generated but didn't copy" | Same as above | Click Generate, copy immediately this time |
| "I pasted but bot says invalid key" | Wrong key type (live vs testnet) or trailing whitespace | Compare first 8 chars to testnet dashboard; ensure `.env` has no quotes around value |
| "Timestamp error" | System clock drift | Sync system clock; ccxt tolerates small drift but not minutes |

## Security guardrails (tell the user, never assume)
- Never share API key/secret in chat
- Never screenshot a screen that shows the key at full width — sensor first or scroll so only balance table is visible
- Testnet API key is fine to regenerate repeatedly; there's no penalty
- `.env` is already in `.gitignore` for this project, so accidental git add won't leak it (but a screenshot might)

## Verification commands
```bash
# After saving .env:
cd /c/Users/<user>/trading-bot
python -c "
from pathlib import Path; from dotenv import load_dotenv; import os
load_dotenv('.env')
print('key len:', len(os.environ.get('EXCHANGE_API_KEY','')))
print('secret len:', len(os.environ.get('EXCHANGE_API_SECRET','')))
"
# Expected: both lengths ~64. Zero means .env didn't load.
```

```bash
# Direct exchange ping (no trade):
python main.py run --mode=live --once --verbose
# Expected final line: "Cycle complete. N trade(s) executed."
# If connection error appears, --verbose will show the ccxt error message.
```
