# KAE — Knowledge Archaeology Engine (Laravel Control Plane)

This repository is the **Laravel product spine** for the Knowledge Archaeology Engine — a Go-based system that autonomously ingests academic sources, runs multi-model LLM reasoning cycles, and surfaces anomalous cross-domain concepts via Qdrant vector storage.

The Go ingestion engine lives at `~/kae`. This Laravel app provides the UI, orchestration, sync jobs, and API surface that wrap it.

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│  LARAVEL (this repo — product spine)                    │
│                                                         │
│  Filament UI → Run/Finding/MetaConcept resources        │
│  Eloquent  → runs, chunks, nodes, meta_concepts,        │
│              findings, gutenberg_blacklists,            │
│              audit_results                              │
│  Jobs      → DispatchIngestionRun (push to Redis)       │
│              Sync* jobs (pull from Qdrant)              │
│              KaeEventPoller (consume run events)        │
│  API       → /api/runs, /api/findings                   │
│  Scheduler → SyncMetaGraph (hourly), Forensics (daily)  │
└────────────────────┬────────────────────────────────────┘
                     │
           Redis kae:run_jobs   (plain JSON)
           Redis kae:run_events (plain JSON)
                     │
┌────────────────────▼────────────────────────────────────┐
│  GO WORKER (~/kae — ingestion engine)                   │
│                                                         │
│  Fetch → Chunk → Classify → Embed → Graph → Qdrant      │
│  Writes: kae_chunks, kae_nodes, kae_meta_graph          │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│  QDRANT  localhost:6333 (REST) / :6334 (gRPC)           │
│                                                         │
│  kae_chunks · kae_nodes · kae_meta_graph                │
│  kae_lens_findings · kae_ensemble_runs                  │
└─────────────────────────────────────────────────────────┘
```

The Go engine handles all pipeline work (fetch, chunk, classify, embed, graph merge). Laravel handles everything else: run creation, config management, findings curation, and the Filament admin UI.

---

## Current System State (as of 2026-04-20)

### Go Engine — Completed Tiers

| Tier | Feature | Status |
|------|---------|--------|
| 1 | Multi-provider support (Anthropic, OpenAI, Gemini, Ollama, OpenRouter) | ✅ |
| 1 | Multi-model ensemble reasoning with controversy scoring | ✅ |
| 1 | Novelty decay detection + auto-stop | ✅ |
| 1 | Auto-branching on high controversy | ✅ |
| 1 | Cross-run anomaly clustering (`--analyze`) | ✅ |
| 1 | Gutenberg ingestion + blacklist | ✅ |
| 2 | Persistent meta-graph across runs (`kae_meta_graph`) | ✅ |
| 2 | Citation chain excavation (Semantic Scholar BFS) | ✅ |
| 2 | Domain boundary detection (bridges + moats) | ✅ |

**Run stats:** 27 autonomous runs · 3,227 concept nodes · 14,912 source chunks

### Qdrant Collections

| Collection | Vectors | Notes |
|-----------|---------|-------|
| `kae_chunks` | 3,629 | Source passages; uint64 numeric IDs |
| `kae_nodes` | 678 | Per-run concept nodes |
| `kae_lens_findings` | 161 | Lens synthesis output; UUID IDs |
| `kae_meta_graph` | 0 | Needs investigation — population bug |
| `vectoreology_findings` | 380 | Legacy findings |

### Known Issues

- `kae_meta_graph` is empty (0 vectors) — meta-graph population not triggering after runs
- 21 contaminated Gutenberg chunks need `contamination_detected: true` payload flag
- Graph building does not yet filter contaminated chunks

---

## Planned: Laravel Migration

The migration from pure Go CLI to a Go + Laravel hybrid is documented in `MIGRATION_ORIENTATION.md`. The plan splits responsibilities cleanly:

**Moves to Laravel:**
- Run creation and seed management (replaces `--seed` flag)
- `gutenberg_blacklist.json` → `GutenbergBlacklist` Eloquent model with Filament CRUD
- LLM provider / cycle config (replaces CLI flags)
- Post-run report storage and display
- Findings browsing and curation
- Cross-run analytics (replaces `kae-analyzer` CLI)
- Data quality monitoring (wraps `kae-forensics`)
- User management and access control (no user concept exists today)
- REST API surface

**Stays in Go:**
- All ingestion pipeline stages (fetch, chunk, classify, embed)
- Qdrant writes (kae_chunks, kae_nodes, kae_meta_graph)
- Lens synthesis daemon
- Forensics auditor

### Target Laravel Models

| Model | Purpose |
|-------|---------|
| `Run` | KAE run record — seed, config, status, report |
| `RunConfig` | Per-run LLM and tuning parameters |
| `Chunk` | Synced mirror of `kae_chunks` payload |
| `Node` | Synced mirror of `kae_nodes` payload |
| `MetaConcept` | Synced mirror of `kae_meta_graph` payload |
| `Finding` | Synced mirror of `kae_lens_findings` |
| `GutenbergBlacklist` | Replaces `gutenberg_blacklist.json` |
| `AuditResult` | Output from `kae-forensics` runs |

### Redis Job Contract

| Direction | Redis Key | Format |
|-----------|-----------|--------|
| Laravel → Go | `kae:run_jobs` | Plain JSON |
| Go → Laravel | `kae:run_events` | Plain JSON |

Laravel pushes to `kae:run_jobs` via `RunOrchestrator`. Go reads with BLPOP. Go pushes status/completion events to `kae:run_events`; a `KaeEventPoller` scheduled job in Laravel consumes them.

---

## Roadmap

### Tier 3 — Autonomous Evolution (planned)
- Self-modifying prompts (prompt evolution based on run analysis)
- Hypothesis generation from anomaly clusters
- Active learning scheduler (adaptive ingestion based on detected anomalies)

### Tier 4 — Visualization & Interface (planned)
- Live graph web UI (Go HTTP + React/D3)
- Reasoning trace replayer
- Natural language query CLI (`kae query "show anomalies related to consciousness"`)

### Tier 5 — Experimental (planned)
- Adversarial dialectic engine (anomaly-seeker vs. mainstream-defender vs. judge)
- Consciousness framework integration (Seth, Cannon, Bashar cross-reference)
- Prediction validation system (track when mainstream "discovers" KAE anomalies)

### Tier 6 — Quick Wins (planned)
- Anomaly RSS feed
- Email digest after each run
- Qdrant automated snapshot backups (daily cron to S3/R2)
- QMU forum auto-post for top anomalies

---

## Setup

### Requirements

- PHP 8.2+, Composer
- Node 20+
- PostgreSQL
- Redis
- Qdrant running on `localhost:6333` / `localhost:6334`
- Go engine built at `~/kae/kae`

### Environment

Copy `.env.example` to `.env` and fill in:

```env
# Database
DB_CONNECTION=pgsql
DB_DATABASE=kae
DB_USERNAME=kae
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Qdrant
QDRANT_URL=http://localhost:6333
QDRANT_GRPC_ADDR=localhost:6334

# LLM providers (passed through to Go worker)
OPENROUTER_API_KEY=
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=

# KAE worker
KAE_RUN_JOBS_KEY=kae:run_jobs
KAE_RUN_EVENTS_KEY=kae:run_events
KAE_WORKER_BIN=/home/mark/kae/kae
```

### Install

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
```

### With Sail

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
```

---

## Go Engine Quick Reference

```bash
# Build all binaries (from kae-lens/)
make build

# Run KAE ingestion
cd ~/kae && ./kae --seed "consciousness" --cycles 20

# Run Lens daemon (TUI)
cd ~/kae/kae-lens && ./lens

# Run Lens headless
./lens --no-tui

# View attractor concepts (appeared in 3+ runs)
./kae --attractors --attractor-min-runs 3

# Domain bridge/moat analysis
./kae --domain-analysis

# Start Qdrant
cd ~/kae/kae-lens && make qdrant-up
```

Config: `kae-lens/config/lens.yaml`

---

## Key Invariants

- **Always use `qdrantclient.PointIDStr(id)` — never `id.GetUuid()`** when extracting IDs from `kae_chunks` points. The collection uses uint64 numeric IDs; `GetUuid()` returns `""` for them.
- Correction chunks (`lens_correction: true`) are permanently excluded from re-processing — `ClearProcessedFlags` skips them.
- Lens marks points `lens_processed=true` *before* reasoning starts (optimistic locking) to prevent duplicate batches.
- Laravel sync jobs use `updateOrCreate(['qdrant_point_id' => ...])` — safe to re-run.

---

## License

Private — not for distribution.
