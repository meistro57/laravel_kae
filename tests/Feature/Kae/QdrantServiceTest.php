<?php

use App\Services\QdrantService;

/**
 * Integration tests that require a live Qdrant instance.
 *
 * Run with: php artisan test --group=integration
 * Skip in CI when Qdrant is unavailable by not passing --group=integration.
 */

function qdrantIsReachable(): bool
{
    $url = config('kae.qdrant.url', 'http://localhost:6333');

    try {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $result = @file_get_contents("{$url}/readyz", false, $ctx);

        return $result !== false;
    } catch (Throwable) {
        return false;
    }
}

it('can reach the kae_chunks collection in live qdrant', function () {
    if (! qdrantIsReachable()) {
        $this->markTestSkipped('Qdrant is not reachable at ' . config('kae.qdrant.url'));
    }

    $qdrant = app(QdrantService::class);

    $collection = $qdrant->getCollection('kae_chunks');

    expect($collection)->not->toBeNull()
        ->and($collection)->toHaveKey('points_count');
})->group('integration');

it('can scroll at least one point from kae_chunks', function () {
    if (! qdrantIsReachable()) {
        $this->markTestSkipped('Qdrant is not reachable at ' . config('kae.qdrant.url'));
    }

    $qdrant = app(QdrantService::class);

    $point = $qdrant->scrollOne('kae_chunks');

    expect($point)->not->toBeNull()
        ->and($point)->toHaveKey('payload');
})->group('integration');

it('lists all expected kae collections', function () {
    if (! qdrantIsReachable()) {
        $this->markTestSkipped('Qdrant is not reachable at ' . config('kae.qdrant.url'));
    }

    $qdrant = app(QdrantService::class);

    $collections = $qdrant->listCollections();

    expect($collections)
        ->toContain('kae_chunks')
        ->toContain('kae_nodes')
        ->toContain('kae_meta_graph');
})->group('integration');
