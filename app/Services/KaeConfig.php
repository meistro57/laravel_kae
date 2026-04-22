<?php

namespace App\Services;

class KaeConfig
{
    // ── Qdrant ───────────────────────────────────────────────────────────────

    public function qdrantUrl(): string
    {
        return config('kae.qdrant.url');
    }

    public function qdrantGrpcAddr(): string
    {
        return config('kae.qdrant.grpc_addr');
    }

    public function qdrantApiKey(): string
    {
        return config('kae.qdrant.api_key') ?? '';
    }

    // ── LLM Providers ───────────────────────────────────────────────────────

    public function openrouterKey(): ?string
    {
        return config('kae.llm.openrouter_key');
    }

    public function anthropicKey(): ?string
    {
        return config('kae.llm.anthropic_key');
    }

    public function openaiKey(): ?string
    {
        return config('kae.llm.openai_key');
    }

    public function geminiKey(): ?string
    {
        return config('kae.llm.gemini_key');
    }

    public function ollamaUrl(): string
    {
        return config('kae.llm.ollama_url');
    }

    // ── Embeddings ───────────────────────────────────────────────────────────

    public function embeddingsUrl(): string
    {
        return config('kae.embeddings.url');
    }

    public function embeddingsKey(): ?string
    {
        return config('kae.embeddings.key');
    }

    public function embeddingsModel(): string
    {
        return config('kae.embeddings.model');
    }

    // ── External Sources ─────────────────────────────────────────────────────

    public function coreApiKey(): ?string
    {
        return config('kae.sources.core_api_key');
    }

    // ── Go Worker Dispatch ───────────────────────────────────────────────────

    public function runJobsKey(): string
    {
        return config('kae.worker.run_jobs_key');
    }

    public function runEventsKey(): string
    {
        return config('kae.worker.run_events_key');
    }

    public function workerBinary(): string
    {
        return config('kae.worker.binary');
    }

    public function workerTimeoutSeconds(): int
    {
        return config('kae.worker.timeout_seconds');
    }

    // ── Collection Names ─────────────────────────────────────────────────────

    public function chunksCollection(): string
    {
        return config('kae.collections.chunks');
    }

    public function nodesCollection(): string
    {
        return config('kae.collections.nodes');
    }

    public function metaCollection(): string
    {
        return config('kae.collections.meta');
    }

    public function findingsCollection(): string
    {
        return config('kae.collections.findings');
    }
}
