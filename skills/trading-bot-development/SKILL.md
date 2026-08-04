---
name: trading-bot-development
description: "Build a modular CLI-based trading bot from scratch with CCXT exchange integration, DCA/Grid strategies, SQLAlchemy database, risk management, and CLI interface."
version: 1.0.0
author: Hermes Agent
tags: [trading, crypto, bot, ccxt, python, cli, backtesting]
---

# Trading Bot Development Skill

Build a modular, extensible trading bot with CCXT for exchange integration, SQLAlchemy for persistence, and Click for CLI.

## When to Use

Trigger when the user asks to:
- "Buat trading bot" / "Build a trading bot"
- "Buat bot DCA / Grid"
- "Trading bot dengan Python"
- "Bot crypto dengan CCXT"
- "Paper trading bot"
- Backtesting of trading strategies
- "Tambah strategi EMA / RSI / indikator teknikal"
- "Buat dashboard untuk trading bot"
- "Deploy bot ke VPS / background service"

## Project Structure

```
<project-root>/
├── main.py                      # Entry point
├── pyproject.toml               # Dependencies (use uv)
├── config/
│   └── config.yaml              # YAML config: exchange, db, strategies, risk, telegram, general
├── data/                        # SQLite DB auto-created here
├── logs/                        # Log files
├── src/
│   ├── cli/__init__.py          # Click CLI: init, run, backtest, status
│   ├── database/
│   │   ├── models.py            # SQLAlchemy ORM (Bot, Trade, Position, Signal, DailySummary)
│   │   └── database.py          # DatabaseManager: PostgreSQL/SQLite, session management
│   ├── exchange/
│   │   ├── __init__.py
│   │   └── adapter.py           # ExchangeAdapter (CCXT) + MockExchange + get_exchange()
│   ├── risk/
│   │   ├── __init__.py
│   │   └── manager.py           # RiskManager: circuit breaker, daily loss, consecutive losses
│   ├── strategies/
│   │   └── __init__.py          # BaseStrategy, DCAStrategy, GridStrategy, StrategyEngine
│   └── notifier.py              # Notifier: ConsoleNotifier + TelegramNotifier + WebhookNotifier
```

## Architecture

### Dependency Injection Pattern
All components receive their dependencies via constructor, not globals:
```python
engine = StrategyEngine(exchange, risk, db_session, notifier)
```

**CRITICAL: Use ONE shared session for all components, especially with SQLite.** Creating separate `db.get_session()` calls for RiskManager and StrategyEngine causes `sqlite3.OperationalError: database is locked`. The SQLite driver only supports one writer at a time.
```python
# ✅ CORRECT: one session, shared
db_session = db.get_session()
risk = RiskManager(config, db_session)
engine = StrategyEngine(exchange, risk, db_session, notifier)
```
```python
# ❌ WRONG: two sessions will deadlock on SQLite
risk = RiskManager(config, db.get_session())
engine = StrategyEngine(exchange, risk, db.get_session(), notifier)
```

### Exchange: Dual Mode (Real + Mock)
- `get_exchange(config)` factory returns `ExchangeAdapter` (real CCXT) or `MockExchange` (paper)
- Mock exchange generates price via random walk + has order book simulation
- Mock supports limit orders (40% instant fill chance), market orders (with slippage), balance management

### Strategy Engine Pattern
- `BaseStrategy` ABC with `evaluate() -> list[signal_dict]` and `get_config_schema()`
- `StrategyEngine` registers strategies, calls `run_cycle()`, logs signals to DB, then executes
- Each signal: {action, symbol, price, amount, reason, confidence, strategy}
- **Strategy registration**: Update `register_strategy()` in `__init__.py` when adding new strategy types. Match by `strategy_type` string from config.

### Adding New Strategies
1. Create `src/strategies/<name>.py` with class extending `BaseStrategy`
2. Override `evaluate()` and `get_config_schema()`
3. **Must import and register in** `src/strategies/__init__.py` `StrategyEngine.register_strategy()`
4. Add config entry in `config/config.yaml` under `strategies` section
5. New strategies need a `get_ohlcv()` method on the exchange — MockExchange must implement this too

### EMA Cross Strategy Details
- Config: symbol, fast_period (9), slow_period (21), position_size_percent
- Uses `pandas.DataFrame.ewm()` for EMA computation (no TA-Lib needed)
- Golden cross: fast EMA crosses ABOVE slow → BUY (confidence: 0.75)
- Death cross: fast crosses BELOW slow → SELL (confidence: 0.75)
- Also trend-follow signals when price pulls back within an active trend (confidence: 0.5)
- Depends on `exchange.get_ohlcv(symbol, '1h', limit=slow_period+50)`

### RSI Mean Reversion Strategy Details
- Config: symbol, rsi_period (14), oversold (30), overbought (70), position_size_percent
- Computes RSI manually using pandas: delta → gain/loss → EWMA → RS → RSI formula
- Six signal conditions: extreme oversold, oversold bounce confirmed, improving oversold, extreme overbought, overbought reversal confirmed, weakening overbought
- Confidence ranges: 0.55 (weak) to 0.75 (crossover confirmed)
- Depends on `exchange.get_ohlcv(symbol, '1h', limit=rsi_period+20)`

### Database: SQLite first, PostgreSQL ready
- `DatabaseManager` with `init_db()` → auto-creates tables
- Context manager `with db.session() as session:` for safe transactions
- Models use SQLAlchemy 2.0 `Mapped` / `mapped_column` style

### CLI: Click-based with Rich formatting
- Commands: `init`, `run --mode=mock|live --once`, `backtest`, `status`
- `--verbose` flag for DEBUG logging with Rich tracebacks

## Dependencies

```bash
uv add ccxt sqlalchemy alembic python-dotenv rich click tabulate pandas numpy pyyaml requests
```

For **PostgreSQL** use `psycopg[binary]` (psycopg3, modern — NOT `psycopg2-binary`). Faster, async-ready, cleaner URL handling.
```bash
uv add psycopg[binary]    # psycopg3 — modern driver (preferred for new projects)
# If migrating from older projects:
uv add psycopg2-binary    # legacy psycopg2 — still works but psycopg3 is better
```

Key dependency: **ccxt** — unified exchange API (supports 100+ exchanges).
Key dependency: **rich** — beautiful CLI output, tables, panels, tracebacks.

## Key Coding Patterns

### MockExchange
- **Price simulation**: `random.gauss(0, self.volatility)` with bounds at 50%-200% of base price
- **Balance management**: Thread-safe with `self._lock`; updates free/used/total on every trade
- **Market orders**: Apply 0-0.05% slippage; check balance before execution
- **Limit orders**: 40% instant fill chance; rest stay open in `self.open_orders` list
- **Tick()**: Call this each cycle to advance price
- **get_ohlcv(symbol, timeframe, limit)**: Generate simulated candles by walking `current_price` backward through `limit` bars. Each bar: open=prev_close, close=open*(1+gauss), high=max*1.003, low=min*0.998. Timestamps work back from `time.time()`. Required by EMA Cross and RSI strategies.

### RiskManager
- `can_open_trade()`: Checks circuit breaker → daily loss → consecutive losses → max open trades → position size
- `check_circuit_breaker()`: Returns (tripped: bool, reason: str)
- Daily tracking via `DailySummary` table (date, total_trades, win_count, loss_count, net_pnl, max_drawdown)

### Strategy Engine
- Signal logging to DB before execution (for audit trail)
- Signal execution catches exceptions per-signal, not per-strategy (one failing signal doesn't kill the batch)
- Supported actions: buy, sell, buy_limit, sell_limit

### SQLite — WAL Mode & Busy Timeout
Enable WAL mode immediately after engine creation to prevent "database is locked":
```python
if self.db_type == "sqlite":
    raw = self.engine.raw_connection()
    raw.execute("PRAGMA journal_mode=WAL")
    raw.execute("PRAGMA busy_timeout=5000")
    raw.close()
```
And set `connect_args={"check_same_thread": False}` on `create_engine()`.
Still, even with WAL, you MUST use ONE shared session object for all components — two `get_session()` calls will deadlock.

### Config Loader — .env + Environment Variables
Create a `ConfigLoader` class that:
1. Loads `config/config.yaml`
2. Overrides from `.env` file (via `python-dotenv`)
3. Overrides from `TRADING_BOT_*` env vars (highest priority)
4. Uses `ENV_MAP` dict to map env var names → dot-notation config paths
5. Supports `get(key: str, default=None)` with nested key traversal
6. Type coercion: "true"/"false" → bool, digits → int, else → str

Environment variable prefix convention: `TRADING_BOT_API_KEY` → maps to `config.exchange.api_key`

### DatabaseManager — Multi-DB with Env Override
The DB manager should ALSO read `DATABASE_TYPE` and `DATABASE_URL` directly from environment (not just from YAML), so users can switch DBs via `.env` without editing `config.yaml`:
```python
def __init__(self, config: dict):
    # Priority: explicit config > env vars > defaults
    self.db_type = (os.environ.get("DATABASE_TYPE") or config.get("type", "sqlite")).lower()
    self.connection_string = (
        os.environ.get("DATABASE_URL")
        or config.get("connection_string", "sqlite:///data/trading_bot.db")
    )

    if self.db_type == "sqlite":
        self.engine = create_engine(self.connection_string, connect_args={"check_same_thread": False})
    else:
        self.engine = create_engine(
            self.connection_string,
            pool_size=10, max_overflow=20,
            pool_pre_ping=True,    # auto-reconnect on connection loss
            pool_recycle=3600,     # recycle stale connections hourly
        )
```

### setup_db.py Pattern (Auto-Create Schema)
Always ship a separate `setup_db.py` script alongside your bot. The user should be able to `python setup_db.py` and have the database ready without manually running migrations. It MUST:
1. Load `.env` first (`load_dotenv`)
2. Check '.env' exists — error with setup instructions if not
3. Branch on `DATABASE_TYPE`:
   - **SQLite**: create `data/` directory, done
   - **PostgreSQL**: connect to `postgres` default DB → check if target DB exists → `CREATE DATABASE` if not → then connect to target DB → run `Base.metadata.create_all(engine)` → list public tables for confirmation
4. Use `psycopg.connect(..., connect_timeout=10)` with explicit `host/port/user/password/dbname` parsed from URL
5. Catch `psycopg.OperationalError` and print **actionable troubleshooting** (service running? creds correct? port 5432 open?) — NOT a generic traceback
6. Print "Setup selesai! Jalankan: python main.py run --mode=mock" at the end

This dramatically reduces "stuck after install" friction. Users coming from Supabase, Laragon, or local Postgres all benefit from a single command that works.

**Reference snippet** for `setup_db.py` PostgreSQL utility — see `references/setup-db-script-template.py` for a complete copy-paste-ready script.

## Reference Files

- `references/setup-db-script-template.py` — Copy-paste-ready `setup_db.py` for SQLite/PostgreSQL auto-setup (with friendly error messages and psql troubleshooting hints).
- `references/binance-testnet-api-key.md` — Step-by-step reproduction guide for generating/regenerating Binance Testnet API key + secret, including stuck-state troubleshooting table and verification commands.
- `references/wrapper-scripts.md` — Triple-platform wrapper pattern (`run-bot.sh`/`.bat`/`.ps1`) for Windows users with shadowed `python` PATH (Hermes Agent venv, Laragon Python, Conda). Hardcodes `.venv/Scripts/python.exe` so the project's real interpreter is always used.
- `references/cli-mode-vs-factory.md` — The dual-key gotcha: CLI `--mode` flag sets `config["exchange"]["mode"]` but factory reads `config.get("mode")`. Canonical wiring to avoid the silent "always mock" bug.

## Mid-Task User Intervention Handling

User may say any of: "stop dahulu" / "bagaimana menjalankan" / "tunjukkan cara" / "show me how to run this first." When this happens mid-implementation:
1. **STOP generating new code** immediately
2. Drop into a tight, executable usage guide (3-step quick start + commands table)
3. Show what already works vs what's pending — be transparent about incomplete parts
4. Offer pivots: continue polishing / finish dashboard / fix specific issue / move to next phase

When user raises a clear blocker ("database is locked" repeatedly), DO NOT keep retrying the same fix. Instead:
1. Acknowledge the blocker honestly
2. Offer 2-3 architectural alternatives (e.g., "Switch to PostgreSQL" not "Try harder to make SQLite work")
3. Wait for their decision before executing more

This conversational pattern is more valuable than completing the original spec. Users building a real product want to validate intermediate state, not get an "almost-finished" deliverable.

## Pitfalls to Avoid

1. **Signal model mismatch**: Signal model has no `amount` field. Pass only `(bot_id, strategy_type, symbol, action, price, confidence, indicators_data, is_executed)`. Do NOT pass `amount` to Signal constructor.

2. **`__table_args__` in DailySummary**: Subagent-generated code may include a broken `__table_args__` with `__table_args__ if False else tuple()` — this causes `SchemaItem' object expected, got ()`. Fix: replace with a clean `__table_args__ = (UniqueConstraint(...),)` or just remove it if not needed.

3. **SQLAlchemy 2.0 async vs sync**: The `Mapped` / `mapped_column` style works with sync sessions. Do NOT mix with async engines.

4. **Config file init path resolution**: `python main.py init` creates config in the wrong place if `__file__` resolution misbehaves. Use explicit `Path(__file__).parent.parent / "config"` to find the project root.

5. **Subagent timeout on large files**: Subagents may time out (600s) when writing large modules. Write simpler modules directly rather than delegating.

6. **ccxt sandbox mode**: For Binance testnet, use `exchange.set_sandbox_mode(True)` — not all exchanges support this. Check ccxt docs per exchange.

7. **Backtest accuracy vs reality**: Mock exchange uses perfect random walk with no real market microstructure. Backtest results are directional indicators only, not performance guarantees.

8. **SQLite "database is locked" with multiple sessions**: SQLite only supports one concurrent writer. Using `db.get_session()` twice creates two separate SQLAlchemy sessions that will deadlock on writes. Fix: create ONE session and share it across all components. Enable WAL mode + busy_timeout on engine init. If the issue persists, switch to PostgreSQL.

9. **Import order in StrategyEngine**: New strategy files must be imported BEFORE the `StrategyEngine` class definition in `src/strategies/__init__.py`. The `register_strategy()` method maps strategy type strings to class constructors — add a new `elif` branch for each new strategy.

10. **`amount` key in signal dict vs Signal model**: The signal dictionary (passed between strategies and engine) contains `amount`, but the database `Signal` model does NOT have that field. Split the concerns: signal dict = {action, symbol, price, **amount**, reason, confidence, strategy}; Signal DB record = {bot_id, strategy_type, symbol, action, price (no amount), confidence, indicators_data}.

11. **`patch` tool on Python files with leading whitespace**: The `patch` tool is fuzzy-match based and can match inside the wrong scope. When modifying Python files containing multi-line function declarations (especially CLI/Click with nested decorators + decorators that wrap multiple lines), the patch may match within a function body and leave indent levels wrong. **Symptom**: `IndentationError: unexpected indent (line NNN, column 8)`. **Fix**: When this happens, do NOT keep retrying smaller patches — read the whole file, then `write_file` the entire corrected section. Alternative: use `execute_code` to do precise string replacements via Python (`content.replace()` with carefully verified old_string/new_string).

12. **Placeholder text corruption in code examples**: When writing Python or shell strings via tools, never embed markdown-style placeholders like `***@host:5432/dbname` directly inside string literals. The literal asterisks become part of the file and break Python syntax (`***@host` looks like Python operators). Either: omit the placeholder entirely in code blocks, or use `your_password_here` style placeholders, or URL-encode the example (`***` → `YOUR_PASSWORD`).

13. **`psycopg2-binary` vs `psycopg[binary]`**: New projects should use psycopg3 (`psycopg[binary]`) — cleaner URL handling, better conn timeout, more modern. Old tutorial code uses `psycopg2` — both work with SQLAlchemy, but driver binaries differ in install size and performance.

14. **CLI `--verbose` flag scope**: The `--verbose` flag is on the top-level Click `group`, NOT on subcommands. So `python main.py --verbose run --once` works, but `python main.py run --once --verbose` errors with "No such option: --verbose". Document the correct invocation clearly in README output examples.

15. **Single get_session() vs DatabaseManagers passed around**: Naively passing the `DatabaseManager` object instead of an actual `Session` to components causes `'DatabaseManager' object has no attribute 'add'`. Even with shared sessions, ALWAYS pass the resolved `Session` to Manager/Engine, not the manager itself.

16. **Don't place Python CLI bot under `www/` of Apache/Nginx servers**: Users coming from PHP/Laravel/Laragon instinct is to put code in the web root — but a **Python CLI app** is not web content. Apache will try to serve it as a static file, won't execute the Python entry point, and `.env` becomes web-accessible (API key leak). **Correct locations**: project root (`~/trading-bot/`), `~/projects/`, or under a web server's parent but NOT in `www/`/`htdocs/`/`public_html/`. Only the future Next.js dashboard (if any) belongs in `www/`.

17. **Laragon Postgres heuristic**: When user says "hanya PostgreSQL tidak aktif di Laragon", they usually mean the Postgres service isn't auto-started. Default Laragon startup order is Apache → MariaDB (not Postgres). Two fixes: start Postgres manually from Laragon Services menu, OR switch DATABASE_URL to MariaDB/MySQL `mysql://root:@localhost:3306/trading_bot` which is auto-started. Mention BOTH options so user picks their environment reality.

18. **Mid-session "stop" / "apakah sudah selesai"**: When user signals status check mid-build, immediately halt code generation and answer with structured status: what's done, what's pending, what's blocked, where files live, next decision point. Don't auto-justify continuing the previous task — sometimes the user is sanity-checking before pivoting.

19. **`patch` tool can leave orphan `***` placeholders in code**: When user (or previous bot edit) copies example URLs like `***@host:5432/dbname` from documentation INTO code literals, the literal asterisks land in the file and break Python compilation. Detect by `grep -n '\*\*\*@' <file>.py` after any patch containing URLs. Fix: use `your_password_here` syntax, env-var concatenation (`postgresql://{user}:{pw}@...`), or URL-encode placeholders.

20. **Patch cascade (retries don't recover from botched indentation)**: When `patch` produces `IndentationError: unexpected indent (line N, column 8)`, retrying with smaller patches almost never converges. Multiple consecutive patches deepen the mess — seen 3+ cycles on this project. Recovery options, in preference order:
    - **Best**: `execute_code` with a precise `content.replace()` using a verified multi-line `old_string` that's unique in the file. Run `python -c "import ast; ast.parse(path)"` after every substitution.
    - **Last resort**: read the entire file, then `write_file` a clean version. Cheap (single tool call), guaranteed correct.
    - **Avoid**: cascading partial patches trying to fix one indent at a time.

21. **Subagent delegation timeout on large code modules**: Both `delegate_task` workers timed out at 600s when writing an exchange adapter (~15KB) and a Next.js dashboard scaffold. The model produced partial code that conflicted with sibling files. Better pattern for big code modules:
    - Write files ≤8KB directly. Predictable, single context, no sibling race.
    - Only delegate boilerplate / scripted config / fixed-template code (≤4KB).
    - If a subagent *does* time out, **read what it wrote** before continuing — it may have created half-files with wrong content.
    - Verify with `python -c "from module import name"` after each working block, not after the whole project.

22. **Binance Testnet API key UI flow varies**: On `testnet.binance.vision`, the API key + secret shows **once** at generation. Subsequent page loads hide the secret behind a "Show Secret" toggle. If user says "I logged in but can't find the key", they likely:
    - Generated it before scrolling — key is below the fold
    - Already closed the modal — must regenerate (old secret is irrecoverable)
    - Looked at the wrong dashboard — live Binance vs testnet Binance are visually similar
    Walk them through: click **Generate HMAC SHA-256 key** → **immediately** copy both fields → paste into `.env` directly, never via chat/email.
23. **One DB session for SQLite (recap with explicit code)**: This tripped the project 3 times. The only safe pattern in `main.py run`:
    ```python
    db = init_database(config["database"])
    db_session = db.get_session()   # ONE call
    risk = RiskManager(config, db_session)
    engine = StrategyEngine(exchange, risk, db_session, notifier)
    # All three components share the same session.
    ```
    Passing `risk.db_session = db_session` is a code smell — already inside RiskManager's `__init__`, just use the same return value. After every cycle, `db_session.commit()` to release the write lock.

24. **CLI `--mode` flag writes to `config["exchange"]["mode"]` but factory reads `config.get("mode")`**: This is a structural drift bug that makes the bot silently always use MockExchange even when `--mode=live` is set, with no error. The user sees "Mode: 💰 LIVE TRADING" in the log, trades execute, SQLite records them — but the testnet balance never changes. Three fixes required together: factory accepts both keys + reads `paper_trading` flag as master, CLI sets both `exchange.mode` AND `general.paper_trading=False`, and a verification log line in the factory (`Using MockExchange` vs `Using real exchange`) that's grep-able. Full audit template in `references/cli-mode-vs-factory.md`.

25. **Triple wrapper scripts (`run-bot.sh`/`.bat`/`.ps1`) for Windows distribution**: When the user's `python` resolves to Hermes Agent venv, Laragon, or Conda (not the project's venv), `python main.py ...` errors with `ModuleNotFoundError` even though `uv sync` succeeded. Ship three wrapper scripts that hardcode `.venv/Scripts/python.exe`, NOT `python` (which still hits PATH). Build a `--help` echo into each wrapper so users discover commands. Always verify the wrapper with `./run-bot.sh main.py --help`, not with bare `python`. Full patterns in `references/wrapper-scripts.md`.

26. **Python CLI bot does NOT belong under `www/` of Apache/Nginx servers**: Even if the user has Laragon/Laravel stack and instinct says "put it in the web root", a Python CLI is not web content. Apache will try to serve `.py` files as static downloads, the entry point won't execute, AND worse — `.env` becomes web-accessible at `http://localhost/.env` (API key leak). Correct locations: project root (`~/trading-bot/`), `~/projects/`, or anywhere except `www/`/`htdocs/`/`public_html/`. Only a future Next.js dashboard belongs in `www/`.

27. **`.env` is overwritten by `config.yaml` if loaded in wrong order**: When CLI calls `load_config()` it reads `config.yaml` first, then `.env` via `python-dotenv` — but the YAML may contain empty-string defaults like `api_key: ""` that immediately overwrite the just-loaded env var. Fix: load `.env` before parsing YAML, AND have `ConfigLoader._apply_env_to_config` set values AFTER YAML parsing, not before. Test: write a key to `.env`, observe it survives `load_config()`.

28. **Mid-session "stop" / "apakah sudah selesai" / interrupt**: When user signals status check mid-build or says they've moved on, immediately halt code generation and answer with structured status: what's done, what's pending, what's blocked, where files live, next decision point. Don't auto-justify continuing the previous task — sometimes the user is sanity-checking before pivoting. Covered above but reinforced: do NOT keep writing code after these signals.

29. **`documentchanged` warning cascade on `patch` tool**: When you call `patch()` and the editor reports "file was modified since last read", read the file fresh BEFORE retrying. Stale patches create cascading false matches. Recovery: `read_file(path, offset=N)` for the whole file then `write_file` for a fresh overwrite — faster than three failed patches.

30. **Skill discovery and reference paths in Hermes**: When patching or writing to a skill's `references/`, `templates/`, `scripts/`, `assets/` folder, the path is **relative to the skill directory**. Use `references/file.md`, not an absolute path. The skill_manage `write_file` action requires `name=<skill>` AND `file_path=references/...` — not just one of them.

## Quick Start

```bash
cd /c/Users/<user>/trading-bot
uv init --app --python 3.11
uv add ccxt sqlalchemy rich click pyyaml pandas numpy python-dotenv ...
python main.py init          # Create default config
# Edit config/config.yaml with your settings
python main.py backtest      # Test with 100 cycles
python main.py run --mode=mock --interval=10  # Live paper trading

# Test new strategies
python main.py --verbose run --mode=mock --once   # Single cycle with debug
```

**On Windows with multiple Python installs** (Hermes Agent venv, Laragon, Conda), use the bundled wrapper instead of bare `python`:
```bash
./run-bot.sh main.py --verbose run --mode=live --once   # git-bash
run-bot.bat main.py --verbose run --mode=live --once    # CMD
.\run-bot.ps1 main.py --verbose run --mode=live --once  # PowerShell
```
See `references/wrapper-scripts.md` for the templates.

## Config Update for New Strategies

When adding strategies to config.yaml, the structure must be:
```yaml
strategies:
  active:
    - dca
    - grid
    - ema_cross
    - rsi
  ema_cross:
    type: ema_cross        # Must match StrategyEngine elif branch
    config:
      symbol: BTC/USDT
      fast_period: 9
      slow_period: 21
  rsi:
    type: rsi
    config:
      symbol: BTC/USDT
      rsi_period: 14
      oversold_threshold: 30
      overbought_threshold: 70
```

The `.env.example` should contain: EXCHANGE_API_KEY, EXCHANGE_API_SECRET, EXCHANGE_NAME, DATABASE_TYPE, DATABASE_URL, TELEGRAM_ENABLED, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, PAPER_TRADING, CHECK_INTERVAL_SECONDS, MAX_OPEN_TRADES, MAX_DAILY_LOSS_PERCENT, MAX_POSITION_SIZE_PERCENT, DEFAULT_STOP_LOSS_PERCENT, DEFAULT_TAKE_PROFIT_PERCENT, MAX_CONSECUTIVE_LOSSES, CIRCUIT_BREAKER_ENABLED, LOG_LEVEL.