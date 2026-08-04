# CLI `--mode` Flag vs Factory `get_exchange()` — The Silent-Always-Mock Bug

A common bug pattern in trading-bot CLIs (and any dual-mode app):

1. User runs `python main.py run --mode=live --once`
2. Log output says `Mode: 💰 LIVE TRADING`
3. ... but the bot silently executes against `MockExchange`
4. Everything seems fine — except no real orders were placed

The bug is structural: the CLI's `--mode` flag writes to one key in the config dict, but the factory function reads from a DIFFERENT key.

## The buggy pattern

```python
# src/cli/__init__.py
@click.command()
@click.option("--mode", type=click.Choice(["mock", "live"]), default="mock")
def run(mode):
    config = load_config()

    # CLI writes to config["exchange"]["mode"]
    if mode == "mock":
        config["exchange"]["mode"] = "mock"
        config["general"]["paper_trading"] = True
    else:
        config["exchange"]["mode"] = "real"   # <-- writes to "mode" key
        # BUT paper_trading is NOT toggled off!

    exchange = get_exchange(config)           # <-- factory reads different key


# src/exchange/adapter.py
def get_exchange(config: dict):
    mode = config.get("mode", "mock")         # <-- reads TOP-LEVEL "mode" (always None)
    if mode == "mock":
        return MockExchange(...)              # <-- always hits this branch
    return ExchangeAdapter(config["exchange"])
```

Result: `config["exchange"]["mode"] = "real"` is set, but `config.get("mode")` returns `None`, which defaults to `"mock"`. The bot prints "LIVE TRADING" but stays on MockExchange forever.

## The fix — canonical pattern

Three changes:

### 1. Factory accepts BOTH key conventions, prefers paper_trading flag

```python
# src/exchange/adapter.py
def get_exchange(config: dict) -> ExchangeAdapter | MockExchange:
    """Factory function to get the right exchange instance."""
    # Accept both: config["mode"] (legacy) and config["exchange"]["mode"] (CLI)
    mode = (
        config.get("mode")
        or config.get("exchange", {}).get("mode", "mock")
    )
    # paper_trading is the master switch — wins over mode flag
    paper_trading = config.get("general", {}).get("paper_trading", True)

    if mode == "mock" or paper_trading:
        logger.info("Using MockExchange (paper trading mode)")
        return MockExchange(config.get("initial_balance"))

    exchange_config = config.get("exchange", {})
    logger.info(f"Using real exchange: {exchange_config.get('name', 'binance')}")
    return ExchangeAdapter(exchange_config)
```

### 2. CLI toggles BOTH `mode` AND `paper_trading` together

```python
# src/cli/__init__.py
if mode == "mock":
    config["exchange"]["mode"] = "mock"
    config["general"]["paper_trading"] = True
else:
    config["exchange"]["mode"] = "real"
    config["general"]["paper_trading"] = False   # <-- critical: turn off paper flag
```

### 3. Verify with a log line that's hard to miss

The factory's INFO log line is the easiest one-shot check:

```
INFO     Using MockExchange (paper trading mode)        ← mock path
INFO     Using real exchange: binance                    ← real path
```

Diff the two — if you see the mock path during `--mode=live`, the bug is back.

## Why this bug is hard to catch

1. The bot DOESN'T error out — it just runs in mock.
2. The UI shows "LIVE TRADING" because Click printed the mode earlier.
3. Trades execute, log lines appear, lifecycle looks normal.
4. SQLite tables happily record everything.
5. User only notices when their testnet balance doesn't change — and even then they may assume Binance's UI is slow.

## Diagnostic commands

When a user reports "the bot says live but nothing happens on Binance":

```bash
# 1. Direct factory probe (no Click involved, no config.yaml mix-in)
python -c "
from pathlib import Path
from dotenv import load_dotenv
load_dotenv()
import os, ccxt
ex = ccxt.binance({
    'apiKey': os.environ.get('EXCHANGE_API_KEY'),
    'secret': os.environ.get('EXCHANGE_API_SECRET'),
})
ex.set_sandbox_mode(True)
print('Binance testnet server time:', ex.fetch_time())
print('USDT balance:', ex.fetch_balance().get('USDT', {}).get('total'))
"
# Expected: server time + balance printed. If you get IP/key error, the API key itself is wrong.

# 2. Check the bot's actual config after CLI parsing
python -c "
from src.cli import load_config
cfg = load_config()
print('exchange.mode =', cfg.get('exchange', {}).get('mode'))
print('general.paper_trading =', cfg.get('general', {}).get('paper_trading'))
"
# Expected for --mode=live: exchange.mode="real", paper_trading=False

# 3. Probe the factory directly
python -c "
from src.exchange.adapter import get_exchange
from src.cli import load_config
cfg = load_config()
cfg['exchange']['mode'] = 'real'
cfg['general']['paper_trading'] = False
ex = get_exchange(cfg)
print(type(ex).__name__)  # Should be ExchangeAdapter
"
```

## Related patterns to apply the same audit

Anywhere your codebase has dual-mode handling, audit for the same split-config bug:

- `--debug` vs `--release` flags
- `--local` vs `--prod` environments
- `--telemetry on/off` switches
- `--cache backend=redis|memory` choices

Even if each individual feature seems trivially wired, the factory-vs-CLI read pattern can drift. Add a verification log line at every factory's first action and make it grep-able.

## Even better — avoid the config-dict bridge entirely

If you find yourself grepping for `config.get("mode")` across dozens of files, consider replacing the dict with a typed `Settings` class:

```python
from dataclasses import dataclass, field
from typing import Optional

@dataclass
class Settings:
    mode: str = "mock"                       # "mock" or "live"
    paper_trading: bool = True
    exchange_name: str = "binance"
    api_key: str = ""
    api_secret: str = ""
    testnet: bool = True
    # ... rest of config

    @classmethod
    def load(cls) -> "Settings":
        from src.cli import load_config
        d = load_config()
        return cls(
            mode=d.get("exchange", {}).get("mode", "mock"),
            paper_trading=d.get("general", {}).get("paper_trading", True),
            exchange_name=d.get("exchange", {}).get("name", "binance"),
            api_key=d.get("exchange", {}).get("api_key", ""),
            # ...
        )

# Then factory:
def get_exchange(settings: Settings):
    if settings.paper_trading or settings.mode == "mock":
        return MockExchange()
    return ExchangeAdapter(settings)
```

This makes the "two different keys for the same value" structural bug impossible — there's one `paper_trading` attribute, period.
