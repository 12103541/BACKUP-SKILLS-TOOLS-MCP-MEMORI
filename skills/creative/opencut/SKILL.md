---
name: opencut
description: "Open-source CapCut alternative with Rust GPU compositor."
---

# OpenCut (Legacy / Classic)

Open-source CapCut alternative. Video editor for web, desktop, mobile. Rust core handles GPU compositing, effects, masks, WASM bindings. Apps are thin UI shells.

**Status:** Archived. Rewrite at `opencut-app/opencut`. Classic still runs at opencut.app.

## Repo Structure

```
opencut-classic/
├── apps/
│   ├── web/        # Next.js (React, TypeScript)
│   └── desktop/    # GPUI (Rust native UI)
├── rust/
│   ├── crates/
│   │   ├── bridge/       # WASM bindings (JS ↔ Rust)
│   │   ├── compositor/   # GPU compositor (wgpu)
│   │   ├── effects/      # Video effects pipeline
│   │   ├── gpu/          # GPU abstraction
│   │   ├── masks/        # Mask/shape system
│   │   └── time/         # Timeline/timecode logic
│   └── wasm/      # wasm-pack build output
└── docs/         # Architecture docs
```

## Prerequisites

- **Bun** (JS runtime/package manager)
- **Rust** (via rustup) + `wasm-pack`, `cargo-watch`
- **Docker** + Docker Compose (for DB/Redis)

## Quick Start (Web)

```bash
cd opencut-classic

# 1. Env
cp apps/web/.env.example apps/web/.env.local

# 2. Infra (Postgres + Redis)
docker compose up -d db redis serverless-redis-http

# 3. Install deps
bun install

# 4. Build WASM (optional, for local rust changes)
bun run build:wasm
cd rust/wasm/pkg && bun link
cd apps/web && bun link opencut-wasm

# 5. Run dev
bun dev:web          # → http://localhost:3000
```

## Quick Start (Desktop)

```bash
# See apps/desktop/README.md for full setup
# Requires Rust toolchain + native deps (GPUI)

moon run desktop:dev   # or: cargo run --bin opencut-desktop
```

## Common Commands

| Task | Command |
|------|---------|
| Web dev server | `bun dev:web` |
| Build WASM | `bun run build:wasm` |
| Watch WASM rebuild | `bun dev:wasm` |
| Type check | `bun run typecheck` |
| Lint | `bun run lint` |
| Test | `bun test` |
| Docker prod | `docker compose up -d` |

## Key Rust Crates (Core Logic)

| Crate | Purpose |
|-------|---------|
| `compositor` | GPU-accelerated timeline rendering (wgpu) |
| `effects` | Blur, color grade, transforms, transitions |
| `masks` | Bezier masks, feather, animation |
| `time` | Timecodes, frame rates, timeline math |
| `bridge` | wasm-bindgen exports for web |
| `gpu` | Cross-platform GPU abstraction |

## WASM Integration (Web)

```bash
# One-time local link
cd rust/wasm && wasm-pack build --target bundler
cd pkg && bun link
cd apps/web && bun link opencut-wasm

# During development
bun dev:wasm  # watches rust/, rebuilds on change
```

Import in TS:
```ts
import { Composer, Effect, Mask } from 'opencut-wasm';
```

## Architecture Notes

- **All logic in Rust** — apps are UI only
- **WASM for web** — `bridge` crate exposes compositor/effects to JS
- **GPUI for desktop** — native Rust UI, same core
- **No business logic in apps/** — only rendering/interaction

## Useful Docs

- `docs/architecture.md` — system overview
- `docs/compositor.md` — GPU pipeline
- `docs/effects.md` — effect graph
- `docs/wasm.md` — bindings guide
- `apps/desktop/README.md` — desktop setup

## Rewrite (opencut-app/opencut)

New architecture: Editor API, plugin-first, Rust core, MCP server, headless mode, scripting tab.
- Repo: https://github.com/opencut-app/opencut
- Preview: https://new.opencut.app
- Track issues/discord for migration timeline