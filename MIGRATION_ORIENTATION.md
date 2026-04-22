# KAE Migration Orientation Report
## Go → Laravel + Go Hybrid

**Date**: 2026-04-20  
**Scope**: Pre-migration technical inventory and target architecture design  
**Status**: Reference document — no code written

---

## PART ONE — The Existing Go Codebase

---

### 1. Module Layout and Entry Points

KAE is four independent Go modules in one repository. Each has its own `go.mod` and binary.

| Path | Module | Binary | Go Version | Role |
|------|--------|--------|-----------|------|
| `/` | `github.com/meistro57/kae` | `./kae` | 1.22.2 | Main ingestion engine — fetch, chunk, embed, graph, store |
| `kae-lens/` | `github.com/meistro/kae` | `./lens` | 1.24.0 | Event-driven reasoner — watches kae_chunks, writes findings |
| `mcp/` | `github.com/meistro57/kae-mcp` | `./kae_mcp` | 1.22 | JSON-RPC MCP server exposing all Qdrant/KAE tools to Claude |
| `kae-analyzer/` | `kae-analyzer` | `./kae-analyzer` | 1.21 | Post-run CLI analytics (cobra subcommands) |
| `kae-forensics/` | `kae-forensics` | `./kae-forensics` | 1.24.0 | Data quality auditor — scans for weak vectors, repairs via re-embed |

**Entry point map:**

- `main.go` (root) — `Engine.Start()`, optional TUI via Bubbletea, emits events on a channel
- `kae-lens/cmd/lens/main.go` — `Watcher` daemon, optional TUI, SSE web server on `:8080`
- `mcp/main.go` — stdin/stdout JSON-RPC 2.0 loop; spawns `./kae` subprocess for `kae_start_run`
- `kae-analyzer/main.go` — cobra CLI, reads directly from Qdrant gRPC
- `kae-forensics/main.go` — audit loop, optional `--repair` mode re-embeds via OpenAI

There is **no HTTP API server** in the current codebase. The only externally-accessible surface is the MCP server (stdin/stdout) and the Lens SSE dashboard (`:8080`, read-only).

There is **no SQL database**. All persistence is Qdrant (vector payloads) or JSON files (graph snapshots, blacklists, reports).

---

### 2. Ingestion Flow End to End

A document travels through six transformation stages before resting in Qdrant. The boundary calls are explicit below.

#### Stage 0 — Seed Selection

```
Input:  --seed flag string  OR  autonomous LLM topic generation
Output: string topic
Struct: (naked string — no domain type yet)
```

When `--seed` is empty, the engine asks the LLM to propose a starting concept. This is **control-plane logic** — it belongs in Laravel (a `Run` model with a `seed` column, seeded by user or by a job).

#### Stage 1 — Source Fetch

```
Input:  topic string, source selector
Output: raw text + metadata
Structs: *ArxivPaper, *WikiResult, *PubMedArticle, *OpenAlexWork, *CorePaper, *GutenbergBook
Boundary: HTTP to external academic APIs (no auth for most; CORE_API_KEY for CORE)
```

Files: `internal/ingestion/{arxiv,wiki,pubmed,openalex,core,gutenberg}.go`

Each fetcher is a standalone function — `ArxivSearch(topic, maxResults)`, `WikiSummary(topic)`, etc. There is no retry logic, no rate limiting, and no fetch deduplication across runs. All six are **pipeline-stage work** (stay in Go).

The `gutenberg_blacklist.json` file (titles to exclude) is **configuration** that should move to a Laravel `GutenbergBlacklist` model with a Filament admin UI.

#### Stage 2 — Chunking

```
Input:  raw text string
Output: []string (overlapping passages)
Function: Chunk(text, maxSize=200, overlap=30)
```

File: `internal/ingestion/helpers.go`

Pure transformation, no I/O. Stays in Go.

#### Stage 3 — Domain Classification

```
Input:  []string chunks, []string source URLs
Output: []DomainResult{Domain string, Confidence float64}
Function: ClassifyDomainBatch(texts, sources, provider llm.Provider)
```

File: `internal/ingestion/classifier.go`

Uses LLM streaming with a structured-extraction prompt. Falls back to URL-pattern heuristics if LLM output is unparseable. This is **pipeline-stage work** (stays in Go). The heuristic fallback rules are a maintenance burden; they could be exposed as a configurable map in Laravel later, but that is out of scope for initial migration.

#### Stage 4 — Embedding

```
Input:  []string chunks
Output: [][]float32 (1536-dim vectors)
Provider: OpenRouter → openai/text-embedding-3-small
Struct: (naked []float32 — no wrapper)
Function: Embedder.EmbedBatch(texts)
```

File: `internal/embeddings/embedder.go`

Single model, single provider, no fallback. **Pipeline-stage work** (stays in Go). The `OPENAI_API_KEY` / `EMBEDDINGS_URL` env vars must be present in the Go worker's environment.

#### Stage 5 — Qdrant Write (kae_chunks)

```
Input:  assembled Chunk struct
Output: upserted point in kae_chunks
Struct: store.Chunk{ID, Text, Source, RunTopic, SemanticDomain, DomainConfidence, RunID, Vector}
Boundary: HTTP PUT /collections/kae_chunks/points (REST to localhost:6333)
```

File: `internal/store/qdrant.go`

This is the primary persistence boundary. After this write, the chunk is visible to Lens, the analyzer, and the MCP server. **Pipeline-stage work** (stays in Go).

**Key invariant (from CLAUDE.md):** Always use `qdrantclient.PointIDStr(id)` — never `id.GetUuid()` — when extracting IDs. `kae_chunks` uses uint64 numeric IDs; `GetUuid()` returns `""` for them.

#### Stage 6 — In-Memory Graph + Node Storage

```
Input:  LLM reasoning output (free-form text stream)
Output: graph.Node, graph.Edge upserted in-process; then flushed to kae_nodes
Structs: Node{ID, Label, Domain, Sources, Weight, Anomaly, Notes, Vector, ContradictionScore}
         Edge{From, To, Relation, Confidence, Citation}
Boundary: Qdrant upsert to kae_nodes (gRPC, localhost:6334)
```

Files: `internal/graph/graph.go`, `internal/store/qdrant.go` (`StoreNodesBatch`)

The in-memory graph is per-run — it is rebuilt from scratch each run and then flushed. Concurrency is protected by `sync.RWMutex`. **Pipeline-stage work** (stays in Go).

#### Stage 7 — Meta-Graph Merge

```
Input:  all nodes from current run
Output: kae_meta_graph upserted (accumulates across runs)
Struct: store.MetaNodeRecord{Concept, FirstSeen, TotalWeight, AvgAnomaly, Domains, IsAttractor, OccurrenceCount, RunOccurrences}
Boundary: Qdrant upsert to kae_meta_graph (REST, localhost:6333)
```

File: `internal/metagraph/` + `internal/store/qdrant.go`

**Pipeline-stage work** (stays in Go). The attractor logic (`OccurrenceCount >= 3 → IsAttractor = true`) is simple enough that it could be replicated in an Eloquent scope later, but the merge itself stays in Go because it requires embedding the concept label.

#### Stage 8 — Lens Post-Processing (async, separate binary)

The Lens daemon runs independently. It polls `kae_chunks` for `lens_processed != true`, applies vector density analysis, calls the LLM for synthesis, and upserts to `kae_lens_findings`.

```
Persistence boundaries:
  Read:  kae_chunks (Qdrant gRPC scroll)
  Write: kae_lens_findings (Qdrant gRPC upsert)
  Write: kae_chunks (set lens_processed=true, write correction chunks)
```

The Lens Synthesizer and Reasoner are **pipeline-stage work** (stays in Go or a Go sub-service). The Lens *finding* itself, once written to Qdrant, is a product artifact — Laravel should expose it via Eloquent.

---

#### Pipeline Summary Table

| Stage | Transform | Struct | Persistence Boundary | Classification |
|-------|-----------|--------|---------------------|----------------|
| 0 | Seed selection | string | None | Control-plane → Laravel |
| 1 | API fetch | *ArxivPaper etc. | HTTP external APIs | Pipeline → Go |
| 2 | Chunking | []string | None | Pipeline → Go |
| 3 | Domain classify | []DomainResult | LLM API (streaming) | Pipeline → Go |
| 4 | Embedding | [][]float32 | OpenAI embedding API | Pipeline → Go |
| 5 | Qdrant chunk write | store.Chunk | Qdrant kae_chunks | Pipeline → Go |
| 6 | Graph + node write | Node, Edge | Qdrant kae_nodes | Pipeline → Go |
| 7 | Meta-graph merge | MetaNodeRecord | Qdrant kae_meta_graph | Pipeline → Go |
| 8 | Lens synthesis | Finding | Qdrant kae_lens_findings | Pipeline → Go (Lens) |

---

### 3. Current Postgres Schema

**There is no Postgres schema.** KAE has no SQL database. All structured state is in Qdrant payload fields (effectively schemaless JSON). The Laravel migration must therefore design the schema from scratch, using Qdrant payload fields as the source of record for column definitions. The canonical field sets from each collection are documented in Section 4 and serve as the starting point for Laravel migrations.

---

### 4. Qdrant Collection Schemas

#### `kae_chunks`

| Field | Type | Notes |
|-------|------|-------|
| `text` | string | Source passage (≤200 chars) |
| `source` | string | URL or title of origin document |
| `run_topic` | string | KAE run's primary topic (NOT semantic_domain — these are distinct) |
| `semantic_domain` | string | LLM-inferred content class (e.g. "Roman History") |
| `domain_confidence` | float64 | Classifier confidence 0.0–1.0 |
| `run_id` | string | Which KAE run ingested this |
| `lens_processed` | bool | Lens has processed this point (set to true before reasoning begins) |
| `lens_correction` | bool | Written by Lens correction pass; permanently excluded from re-processing |

Vector: 1536-dim, Cosine distance. IDs: uint64 numeric (hash of text content).

Filters in use: `lens_processed != true` (Lens watcher); `lens_correction != true` (ClearProcessedFlags skips these).

#### `kae_nodes`

| Field | Type | Notes |
|-------|------|-------|
| `label` | string | Canonical concept name |
| `domain` | string | Semantic domain |
| `run_id` | string | Originating run |
| `weight` | float64 | Importance/frequency score |
| `anomaly` | bool | Flagged anomalous by contradiction scoring |
| `sources` | []string | Citation URLs |
| `cycle` | int | Engine cycle this node first appeared in |

Vector: 1536-dim, Cosine. IDs: hash-based uint64 from label.

#### `kae_meta_graph`

| Field | Type | Notes |
|-------|------|-------|
| `concept` | string | Canonical concept (deduplicated across runs) |
| `first_seen` | int64 | Unix timestamp |
| `total_weight` | float64 | Cumulative weight across all runs |
| `avg_anomaly` | float64 | Rolling average anomaly score |
| `domains` | []string | All semantic domains this concept has appeared in |
| `is_attractor` | bool | True when occurrence_count >= 3 |
| `occurrence_count` | int | Distinct runs this concept appeared in |
| `run_occurrences` | string | JSON array of {run_id, cycle, weight, anomaly} |

Vector: 1536-dim, Cosine. IDs: hash-based uint64 from concept name.

#### `kae_lens_findings`

| Field | Type | Notes |
|-------|------|-------|
| `finding` | string | Human-readable insight |
| `confidence` | float64 | LLM-assigned confidence 0.0–1.0 |
| `sources` | []string | Supporting chunk IDs or URLs |
| `density_assessment` | string | very_sparse / sparse / medium / dense / very_dense |
| `created_at` | int64 | Unix timestamp |
| `anchor_id` | string | kae_chunks point ID that triggered this finding |
| `reasoning_model` | string | Model that produced this finding |

Vector: 1536-dim, Cosine. IDs: UUID.

---

### 5. LLM Abstraction Layer

#### Provider Interface

```go
type Provider interface {
    Stream(system string, messages []Message) <-chan Chunk
    ModelName() string
}
// Chunk.Type: ChunkText | ChunkThink | ChunkDone | ChunkError
```

All providers implement streaming only. There is no non-streaming path. Structured output is extracted from the stream by prompt engineering — there is no JSON schema enforcement or tool-use/function-calling.

#### Providers Wired Up

| Provider | Key Env Var | Models | Notes |
|----------|------------|--------|-------|
| OpenRouter | `OPENROUTER_API_KEY` | Any model on OR; defaults deepseek/deepseek-r1, gemini-2.5-flash | Used for both reasoning and embedding |
| Anthropic | `ANTHROPIC_API_KEY` | claude-opus-4-6, claude-sonnet-4, claude-haiku-3 | Streams thinking blocks as `ChunkThink` |
| OpenAI | `OPENAI_API_KEY` | gpt-4o, gpt-4-turbo | Reuses OpenAI-compat streaming |
| Gemini | `GEMINI_API_KEY` | gemini-2.5-flash, gemini-pro | Native Gemini wire format |
| Ollama | `OLLAMA_URL` (default localhost:11434) | Any local model | No key required |

Factory: `NewProvider("provider:model", keys)` — parses the colon-delimited string and returns the appropriate implementation.

#### Structured Output Handling

There is no structured output enforcement. The `ClassifyDomainBatch` prompt asks for JSON, extracts it by scanning the stream, and falls back to URL heuristics on parse failure. The same pattern applies everywhere: free-form LLM output, regex/JSON extraction, fallback.

#### Cost Tracking

None. The `-model` / `-fast` flag split (expensive reasoner vs. cheap bulk) is the only cost control. No token counting, no cost accumulation, no alerting.

#### Caching

None at the LLM layer. There is an embedding cache in the Embedder but it is in-process only (not persisted across runs).

---

### 6. Control-Plane Code — The "Migrate to Laravel" List

These are the things that are currently either missing, hardcoded, or embedded in CLI flags that belong in a product layer:

| Current Form | Control-Plane Concern | Laravel Destination |
|---|---|---|
| `--seed` flag / LLM auto-generation | Run creation, seed management | `Run` model + Filament create form |
| `gutenberg_blacklist.json` file | Content policy / curation list | `GutenbergBlacklist` Eloquent model + Filament CRUD |
| `--model` / `--fast` flag | LLM provider selection | `RunConfig` or per-run `settings` JSON column |
| `--cycles`, `--stagnation-window` | Run tuning parameters | `RunConfig` model or run `settings` |
| `--ensemble`, `--models` | Multi-model configuration | Per-run config |
| Post-run report (plain text to stderr) | Report generation and storage | `RunReport` model, Filament show page |
| Lens findings (in Qdrant only) | Findings display and curation | `Finding` Eloquent model (synced from Qdrant) |
| `kae-analyzer` CLI subcommands | Analytics and cross-run comparison | Laravel service classes, Filament dashboard widgets |
| `kae-forensics` audit results | Data quality monitoring | Laravel scheduled job + `AuditResult` model |
| No user concept | Multi-tenancy / access control | Laravel `User`, Filament Shield |
| No API surface | Integration / webhook delivery | Laravel API routes |
| `.env` file per binary | Secrets management | Unified `.env` + shared config service |

---

### 7. External Dependencies and Environment Variables

Both runtimes must have access to these:

#### Services

| Service | Protocol | Default Address | Who Uses It |
|---------|---------|----------------|-------------|
| Qdrant | REST HTTP | `localhost:6333` | Go (ingestion write), Laravel (read for sync), MCP |
| Qdrant | gRPC | `localhost:6334` | Go (Lens, Forensics, Analyzer) |

There is no Redis, no Postgres, no message queue in the current stack. Both will be **introduced** as part of the migration.

#### Environment Variables

| Variable | Used By | Purpose |
|----------|---------|---------|
| `OPENROUTER_API_KEY` | Go engine, Lens | Primary LLM provider |
| `ANTHROPIC_API_KEY` | Go engine, Lens | Anthropic Claude provider |
| `OPENAI_API_KEY` | Go engine, Lens, Forensics | OpenAI models + embedding repairs |
| `GEMINI_API_KEY` | Go engine | Gemini provider |
| `OLLAMA_URL` | Go engine | Local Ollama endpoint |
| `QDRANT_URL` | Go engine | Qdrant REST endpoint |
| `CORE_API_KEY` | Go engine (optional) | CORE academic API; silently skipped if absent |

Variables not yet present (must be added):

| Variable | Purpose |
|----------|---------|
| `REDIS_URL` | Job queue for Laravel → Go dispatch |
| `DB_*` (Laravel standard) | Laravel Postgres connection |
| `APP_KEY` | Laravel encryption |
| `QDRANT_GRPC_ADDR` | Go gRPC endpoint (currently hardcoded in Lens) |

---

## PART TWO — The Laravel Target Architecture

---

### 1. Laravel Package Layout

```
laravel/
├── app/
│   ├── Models/
│   │   ├── Run.php                  # KAE run record (seed, config, status, report)
│   │   ├── RunConfig.php            # Per-run LLM/tuning parameters
│   │   ├── Chunk.php                # Synced mirror of kae_chunks payload
│   │   ├── Node.php                 # Synced mirror of kae_nodes payload
│   │   ├── MetaConcept.php          # Synced mirror of kae_meta_graph payload
│   │   ├── Finding.php              # Synced mirror of kae_lens_findings
│   │   ├── GutenbergBlacklist.php   # Replaces gutenberg_blacklist.json
│   │   └── AuditResult.php          # kae-forensics run output
│   │
│   ├── Filament/
│   │   └── Resources/
│   │       ├── RunResource.php          # Create / list / show runs
│   │       ├── FindingResource.php      # Browse, curate, search findings
│   │       ├── MetaConceptResource.php  # Browse attractors, domain bridges
│   │       ├── NodeResource.php         # Anomaly review
│   │       └── GutenbergBlacklistResource.php
│   │
│   ├── Jobs/
│   │   ├── DispatchIngestionRun.php     # Pushes run job onto Redis queue for Go
│   │   ├── SyncChunksFromQdrant.php     # Pulls new kae_chunks into Chunk model
│   │   ├── SyncFindingsFromQdrant.php   # Pulls new kae_lens_findings into Finding
│   │   ├── SyncMetaGraphFromQdrant.php  # Pulls kae_meta_graph into MetaConcept
│   │   └── RunForensicsAudit.php        # Triggers kae-forensics, stores AuditResult
│   │
│   ├── Services/
│   │   ├── QdrantService.php            # HTTP client wrapping Qdrant REST API
│   │   ├── RunOrchestrator.php          # Creates Run, dispatches job, handles lifecycle
│   │   ├── FindingCurator.php           # Business logic: filter, rank, deduplicate findings
│   │   └── CrossRunAnalytics.php        # Replaces kae-analyzer logic in PHP
│   │
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── RunController.php    # POST /api/runs, GET /api/runs/{id}
│   │           └── FindingController.php # GET /api/findings
│   │
│   └── Console/
│       └── Commands/
│           └── KaeSyncQdrant.php        # artisan kae:sync — manual pull from Qdrant
│
├── database/migrations/
│   ├── 0001_create_runs_table.php
│   ├── 0002_create_run_configs_table.php
│   ├── 0003_create_chunks_table.php
│   ├── 0004_create_nodes_table.php
│   ├── 0005_create_meta_concepts_table.php
│   ├── 0006_create_findings_table.php
│   ├── 0007_create_gutenberg_blacklists_table.php
│   └── 0008_create_audit_results_table.php
│
└── config/
    └── kae.php    # QDRANT_URL, GO_WORKER_QUEUE, etc.
```

#### Key Eloquent Models — Column Specifications

**`runs`**
```
id                uuid PK
seed              string nullable
status            enum(pending, running, completed, failed)
started_at        timestamp nullable
completed_at      timestamp nullable
report_text       text nullable
run_id_go         string unique   -- matches run_id in Qdrant payloads
settings          json            -- replaces CLI flags
created_at / updated_at
```

**`run_configs`** (or inline as `settings` json on runs)
```
id              bigint PK
run_id          uuid FK runs
model           string
fast_model      string
max_cycles      int
stagnation_window int
novelty_threshold float
branch_threshold  float
ensemble_enabled  bool
ensemble_models   json   -- ["provider:model", ...]
sources_enabled   json   -- ["arxiv", "wiki", ...]
```

**`chunks`** (mirror of kae_chunks Qdrant payload)
```
id                    bigint PK
qdrant_point_id       bigint unique
run_id                uuid FK runs (nullable — lookup by run_id_go)
text                  text
source                string
run_topic             string
semantic_domain       string
domain_confidence     float
lens_processed        bool default false
lens_correction       bool default false
synced_at             timestamp
```

**`nodes`** (mirror of kae_nodes)
```
id              bigint PK
qdrant_point_id bigint unique
run_id          uuid FK runs
label           string
domain          string
weight          float
anomaly         bool
sources         json
cycle           int
synced_at       timestamp
```

**`meta_concepts`** (mirror of kae_meta_graph)
```
id               bigint PK
qdrant_point_id  bigint unique
concept          string unique
first_seen_at    timestamp
total_weight     float
avg_anomaly      float
domains          json
is_attractor     bool
occurrence_count int
run_occurrences  json
synced_at        timestamp
```

**`findings`** (mirror of kae_lens_findings)
```
id                 uuid PK
qdrant_point_id    string   -- UUIDs in Qdrant
run_id             uuid FK runs nullable
finding            text
confidence         float
sources            json
density_assessment string
anchor_chunk_id    bigint FK chunks nullable
reasoning_model    string
created_at         timestamp
synced_at          timestamp
```

**`gutenberg_blacklists`** (replaces gutenberg_blacklist.json)
```
id              bigint PK
title           string
reason          text
detection_date  date
active          bool default true
created_at / updated_at
```

---

### 2. Contracts-as-Code Strategy

#### The Problem

Laravel's migrations define the SQL schema. The Go worker reads and writes Qdrant payloads. These two representations of the same domain objects must stay in sync. There is no code-generation link between them today because KAE has no SQL schema.

Three options:

**Option A — Shared JSON Schema**  
Write one `schema/kae.schema.json` file that defines all payload field names and types. A Laravel artisan command generates migration stubs from it. A Go linter reads it and checks struct tags. Requires upfront tooling investment.

**Option B — Schema-Generation Tool**  
Write a Go CLI (`kae-schema-gen`) that reads the Qdrant store structs via reflection and emits a JSON Schema or a Laravel migration stub. Keeps Go as the source of truth. Requires the tool to be run on every struct change.

**Option C — Manual Discipline with a Verification Test**  
Keep the Qdrant payload field names in Go structs (e.g., `json:"semantic_domain"`) and an identical column in Laravel migrations. Write a single Laravel Feature test (`KaeContractTest`) that scrolls a known Qdrant collection and asserts that every payload key maps to a known column on the corresponding Eloquent model. Run in CI. If Go adds a field and the migration is not updated, the test fails.

**Recommendation: Option C — manual discipline with a contract test.**

Rationale: The KAE payload schemas change rarely and have few fields (6–10 per collection). A generation tool buys little over a simple test, and JSON Schema is a new file format neither codebase owns natively. The contract test is cheap, runs in CI, catches drift at the only boundary that matters (sync jobs), and requires no new tooling. Add a comment in every relevant Go struct file pointing to the Laravel migration it mirrors. That is the entire discipline.

Contract test sketch:
```php
// tests/Feature/KaeContractTest.php
it('kae_chunks payload matches chunks table columns', function () {
    $sample = QdrantService::scrollOne('kae_chunks');
    $knownColumns = Schema::getColumnListing('chunks');
    $payloadKeys = collect($sample['payload'])->keys()
        ->map(fn($k) => $k); // qdrant key → column name mapping
    foreach ($payloadKeys as $key) {
        expect($knownColumns)->toContain($key);
    }
});
```

Add the same test for each Qdrant collection. The mapping table (Qdrant key → Laravel column) lives as a constant in `KaeContractTest` and is the single authoritative record of the contract.

---

### 3. Redis-Based Job Dispatch

#### Laravel → Go (Dispatch)

Laravel enqueues a job. The Go worker listens on the same Redis list.

**Queue name**: `kae_ingestion` (Laravel queue connection: `redis`, queue: `kae_ingestion`)

**Job payload** (JSON, pushed by `DispatchIngestionRun`):
```json
{
  "type": "start_run",
  "run_id": "uuid-from-laravel",   // Laravel Run model primary key
  "run_id_go": "run_20260420_abc", // Go-style ID written to Qdrant payloads
  "seed": "consciousness",
  "settings": {
    "model": "deepseek/deepseek-r1",
    "fast_model": "google/gemini-2.5-flash",
    "max_cycles": 0,
    "stagnation_window": 3,
    "sources": ["arxiv", "wiki", "pubmed"]
  }
}
```

Laravel dispatches: `DispatchIngestionRun::dispatch($run)->onQueue('kae_ingestion')`

Go worker reads from Redis using BLPOP (block until a job arrives). On receipt:
1. Deserialize JSON payload
2. Map `settings` to `Config` struct
3. `Engine.Start(config)` — headless, no TUI
4. On completion, push a status event back to Laravel

**Go worker** reads from the key `queues:kae_ingestion` (Laravel's Redis queue format: `BLPOP queues:kae_ingestion 0`). Payload is a JSON envelope; the actual job payload is in `data.command` (serialized PHP). To avoid coupling to PHP serialization, dispatch with `->withoutSerializing()` or use a `dispatch()->onQueue()` pattern that emits raw JSON. **Use Laravel's `dispatchNow` with a raw Redis push to a plain JSON list, not the default PHP-serialized queue.** This is the contract boundary — document it explicitly.

Simpler alternative: use a dedicated Redis key `kae:jobs` with plain JSON, pushed by a `RunOrchestrator` service method, not the Laravel job queue. The Laravel queue system is then used only for internal Laravel jobs (sync jobs, etc.). This avoids the PHP serialization problem entirely.

**Recommended approach**: Two separate Redis structures.

| Direction | Mechanism | Redis Key | Format |
|-----------|-----------|-----------|--------|
| Laravel → Go | `RunOrchestrator::dispatch()` calls `Redis::rpush('kae:run_jobs', json_encode($payload))` | `kae:run_jobs` | Plain JSON |
| Go → Laravel | Go calls `Redis::rpush('kae:run_events', json_encode($event))` | `kae:run_events` | Plain JSON |

A Laravel `KaeEventPoller` job (runs every 10 seconds via the scheduler) consumes `kae:run_events` and updates `Run` models accordingly.

#### Go → Laravel (Status Events)

Go pushes events as it progresses:

```json
// Status update event
{
  "type": "run_status",
  "run_id": "uuid-from-laravel",
  "run_id_go": "run_20260420_abc",
  "status": "running",
  "cycle": 3,
  "nodes": 47,
  "timestamp": 1745193600
}

// Completion event
{
  "type": "run_complete",
  "run_id": "uuid-from-laravel",
  "run_id_go": "run_20260420_abc",
  "status": "completed",
  "total_cycles": 12,
  "total_nodes": 234,
  "report_text": "...",
  "timestamp": 1745193900
}

// Error event
{
  "type": "run_error",
  "run_id": "uuid-from-laravel",
  "error": "LLM timeout after 120s",
  "timestamp": 1745193700
}
```

The `KaeEventPoller` job in Laravel processes `kae:run_events` with LPOP in a loop until the list is empty, updates the `Run` model, and triggers `SyncChunksFromQdrant` / `SyncFindingsFromQdrant` on completion.

#### Sync Jobs (Qdrant → Laravel)

These run on completion events and on a scheduled basis:

| Job | Trigger | Action |
|-----|---------|--------|
| `SyncChunksFromQdrant` | run_complete event | Scroll kae_chunks filtered by run_id_go; upsert into chunks table |
| `SyncFindingsFromQdrant` | run_complete event | Scroll kae_lens_findings; upsert into findings table |
| `SyncMetaGraphFromQdrant` | Scheduled (hourly) | Scroll kae_meta_graph; upsert into meta_concepts table |
| `RunForensicsAudit` | Scheduled (daily) | Invoke kae-forensics binary; store results in audit_results |

All sync jobs use Qdrant REST (`QdrantService`) via scroll + filter. Upsert with `updateOrCreate(['qdrant_point_id' => ...])`.

---

### 4. Environment Variable Strategy

#### Principles

1. **Single `.env` file at the Laravel root** is the canonical source for all secrets. Go reads it directly using `godotenv` on startup, the same as it does today.
2. **Go worker is launched by Laravel** (via `RunOrchestrator` or a supervisor), so it inherits the process environment. The Go binary does not need its own `.env` if the supervisor is configured correctly.
3. **Laravel `config/kae.php`** reads all KAE-specific variables via `env()` and returns a typed config array. No `env()` calls outside config files — standard Laravel discipline.

#### Complete Variable Registry

```env
# ─── Database (Laravel) ───────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kae
DB_USERNAME=kae
DB_PASSWORD=

# ─── Laravel ──────────────────────────────────────────
APP_KEY=
APP_URL=http://localhost
APP_ENV=local

# ─── Redis (shared by Laravel queue + Go worker) ──────
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

# ─── Qdrant (shared by Laravel sync + Go worker) ──────
QDRANT_URL=http://localhost:6333
QDRANT_GRPC_ADDR=localhost:6334
QDRANT_API_KEY=                       # empty for local dev

# ─── LLM Providers (Go worker) ────────────────────────
OPENROUTER_API_KEY=
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
GEMINI_API_KEY=
OLLAMA_URL=http://localhost:11434

# ─── Embeddings (Go worker + kae-forensics) ───────────
EMBEDDINGS_URL=https://openrouter.ai/api/v1
EMBEDDINGS_KEY=${OPENROUTER_API_KEY}
EMBEDDINGS_MODEL=openai/text-embedding-3-small

# ─── External Data Sources (Go worker) ───────────────
CORE_API_KEY=                         # optional; silently skipped if absent

# ─── KAE Dispatcher (Laravel → Go) ───────────────────
KAE_RUN_JOBS_KEY=kae:run_jobs
KAE_RUN_EVENTS_KEY=kae:run_events
KAE_WORKER_BIN=/path/to/kae           # absolute path to Go binary
KAE_WORKER_TIMEOUT=3600               # max run time in seconds
```

#### How Go Reads Laravel's Config

The Go worker will be updated to read `REDIS_HOST` / `REDIS_PORT` for the job queue and `QDRANT_URL` for its REST client. It already uses `godotenv.Load()` from the repo root. Ensure the `KAE_WORKER_BIN` is built and the working directory at launch is the KAE repo root so `.env` is found.

For production/CI: do not use `.env` files. Inject all variables as process environment variables. Go and Laravel both respect `os.Getenv` / `env()` without `.env` loading.

---

### Boundary Summary

```
┌─────────────────────────────────────────────────────────┐
│  LARAVEL (Product Spine)                                │
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
           Redis kae:run_jobs (plain JSON)
           Redis kae:run_events (plain JSON)
                     │
┌────────────────────▼────────────────────────────────────┐
│  GO WORKER (Ingestion Engine — Stages 0–7)              │
│                                                         │
│  Consumes: kae:run_jobs (BLPOP)                         │
│  Produces: kae:run_events (RPUSH)                       │
│  Writes:   kae_chunks, kae_nodes, kae_meta_graph        │
│            (Qdrant REST + gRPC)                         │
│  Reads:    gutenberg_blacklist.json                     │
│            → eventually replaced by Laravel API call    │
│  Retains:  fetch, chunk, classify, embed, graph merge   │
│  Removes:  seed selection UI, config flags, report gen  │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│  QDRANT (shared state, read by both runtimes)           │
│                                                         │
│  kae_chunks        written by Go, read by Laravel sync  │
│  kae_nodes         written by Go, read by Laravel sync  │
│  kae_meta_graph    written by Go, read by Laravel sync  │
│  kae_lens_findings written by Lens, read by Laravel sync│
└─────────────────────────────────────────────────────────┘
```

---

*End of orientation report. This document is the reference for all subsequent migration prompts. Update it when decisions are revisited.*
