<?php

use App\Services\KaeConfig;

it('config/kae.php has an accessor for every variable in the orientation report', function () {
    $kae = app(KaeConfig::class);

    // ── Qdrant ───────────────────────────────────────────────────────────────
    expect($kae->qdrantUrl())->toBeString()->not->toBeEmpty();
    expect($kae->qdrantGrpcAddr())->toBeString()->not->toBeEmpty();
    expect($kae->qdrantApiKey())->toBeString(); // may be empty string in dev

    // ── LLM Providers ────────────────────────────────────────────────────────
    // Keys may be null in test env; assert the method exists and returns string|null
    expect($kae->openrouterKey())->toBeNullableString();
    expect($kae->anthropicKey())->toBeNullableString();
    expect($kae->openaiKey())->toBeNullableString();
    expect($kae->geminiKey())->toBeNullableString();
    expect($kae->ollamaUrl())->toBeString()->not->toBeEmpty();

    // ── Embeddings ────────────────────────────────────────────────────────────
    expect($kae->embeddingsUrl())->toBeString()->not->toBeEmpty();
    expect($kae->embeddingsKey())->toBeNullableString();
    expect($kae->embeddingsModel())->toBeString()->not->toBeEmpty();

    // ── External Sources ──────────────────────────────────────────────────────
    expect($kae->coreApiKey())->toBeNullableString();

    // ── Go Worker Dispatch ────────────────────────────────────────────────────
    expect($kae->runJobsKey())->toBeString()->not->toBeEmpty();
    expect($kae->runEventsKey())->toBeString()->not->toBeEmpty();
    expect($kae->workerBinary())->toBeString()->not->toBeEmpty();
    expect($kae->workerTimeoutSeconds())->toBeInt()->toBeGreaterThan(0);

    // ── Collection Names ──────────────────────────────────────────────────────
    expect($kae->chunksCollection())->toBe('kae_chunks');
    expect($kae->nodesCollection())->toBe('kae_nodes');
    expect($kae->metaCollection())->toBe('kae_meta_graph');
    expect($kae->findingsCollection())->toBe('kae_lens_findings');
});

it('kae config values are not read from env() outside config/kae.php', function () {
    // Spot-check that the service reads through config(), not env() directly.
    // This ensures config caching works correctly in production.
    $kae = app(KaeConfig::class);

    // Override config at runtime — if the service used env() directly
    // this override would be ignored.
    config(['kae.qdrant.url' => 'http://overridden:9999']);

    expect($kae->qdrantUrl())->toBe('http://overridden:9999');

    // Restore
    config(['kae.qdrant.url' => env('QDRANT_URL', 'http://localhost:6333')]);
});
