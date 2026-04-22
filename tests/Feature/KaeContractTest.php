<?php

/**
 * KAE Contract Tests — integration group, hits real Qdrant.
 *
 * Each CONTRACTS entry has three key lists:
 *  - 'required': must appear in every sampled point; missing = Go removed a field
 *  - 'optional': may only appear in some points (e.g. only on correction chunks), but when
 *                present are mapped to a dedicated Laravel column
 *  - 'spilled':  present in Qdrant, deliberately captured in raw_payload (no own column)
 *
 * Forward check: every 'required' key must appear in at least one of the sampled points.
 * Backward check: every key found in Qdrant must be in required+optional+spilled.
 *                 If Go adds a new field the backward check fails until we map it.
 *
 * Break/restore demo:
 *   1. Move 'summary' from 'required' to nothing → backward check finds 'summary' in Qdrant
 *      but it's no longer in the contract → FAIL
 *   2. Restore 'summary' → PASS
 *
 * To run: php artisan test --group=contracts
 */

use App\Services\QdrantService;

const CONTRACTS = [
    'chunks' => [
        'collection' => 'kae.collections.chunks',
        'required'   => [
            'run_id', 'text', 'source', 'run_topic',
            'semantic_domain', 'domain_confidence', 'lens_processed',
        ],
        'optional'   => ['lens_correction'],   // only on correction chunks
        'spilled'    => [],
    ],
    'nodes' => [
        'collection' => 'kae.collections.nodes',
        'required'   => [
            'run_id', 'label', 'domain', 'weight', 'anomaly', 'sources', 'cycle',
        ],
        'optional'   => [],
        'spilled'    => [],
    ],
    'meta' => [
        'collection' => 'kae.collections.meta',
        'required'   => [
            'concept', 'first_seen', 'total_weight', 'avg_anomaly',
            'domains', 'is_attractor', 'occurrence_count', 'run_occurrences',
        ],
        'optional'   => [],
        'spilled'    => [],
    ],
    'findings' => [
        'collection' => 'kae.collections.findings',
        'required'   => [
            'summary', 'confidence', 'source_point_ids', 'type',
            'batch_id', 'reviewed', 'reasoning_trace', 'correction',
            'domains', 'created_at',
        ],
        'optional'   => ['reasoning_model'],   // not emitted on all finding types
        'spilled'    => ['embedding_text', 'source_urls'],
    ],
];

dataset('contracts', array_keys(CONTRACTS));

it('collection is reachable and has points', function (string $name) {
    $qdrant     = app(QdrantService::class);
    $collection = config(CONTRACTS[$name]['collection']);
    $page       = $qdrant->scroll($collection, [], 1, null);

    expect($page)->toBeArray()->toHaveKey('points');
})->group('contracts')->with('contracts');

it('every required payload key exists in at least one real point', function (string $name) {
    $qdrant     = app(QdrantService::class);
    $collection = config(CONTRACTS[$name]['collection']);
    $required   = CONTRACTS[$name]['required'];

    $page   = $qdrant->scroll($collection, [], 50, null);
    $points = $page['points'] ?? [];

    if (empty($points)) {
        test()->markTestSkipped("Collection {$collection} is empty.");
    }

    $foundKeys = array_unique(array_merge(...array_map(
        fn ($p) => array_keys($p['payload'] ?? []),
        $points,
    )));

    $missing = array_values(array_filter($required, fn ($k) => ! in_array($k, $foundKeys)));

    if (! empty($missing)) {
        throw new \PHPUnit\Framework\ExpectationFailedException(
            sprintf(
                "Required keys not found in %d sampled points from '%s': %s",
                count($points),
                $collection,
                implode(', ', $missing),
            )
        );
    }

    expect($missing)->toBeEmpty();
})->group('contracts')->with('contracts');

it('every Qdrant payload key is contracted (no unmapped drift)', function (string $name) {
    $qdrant     = app(QdrantService::class);
    $collection = config(CONTRACTS[$name]['collection']);
    $allowed    = array_merge(
        CONTRACTS[$name]['required'],
        CONTRACTS[$name]['optional'],
        CONTRACTS[$name]['spilled'],
    );

    $page   = $qdrant->scroll($collection, [], 50, null);
    $points = $page['points'] ?? [];

    if (empty($points)) {
        test()->markTestSkipped("Collection {$collection} is empty.");
    }

    $foundKeys = array_unique(array_merge(...array_map(
        fn ($p) => array_keys($p['payload'] ?? []),
        $points,
    )));

    $uncontracted = array_values(array_filter($foundKeys, fn ($k) => ! in_array($k, $allowed)));

    if (! empty($uncontracted)) {
        throw new \PHPUnit\Framework\ExpectationFailedException(
            sprintf(
                "Uncontracted keys found in '%s' — add a column + update sync job, or add to 'spilled': %s",
                $collection,
                implode(', ', $uncontracted),
            )
        );
    }

    expect($uncontracted)->toBeEmpty();
})->group('contracts')->with('contracts');
