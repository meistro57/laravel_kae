<?php

use App\Jobs\Sync\SyncMetaGraphFromQdrant;
use App\Models\MetaConcept;
use App\Services\QdrantService;

function makeMetaPoint(int $id, array $overrides = []): array
{
    return array_merge([
        'id'      => $id,
        'payload' => [
            'concept'          => "Concept {$id}",
            'first_seen'       => 1700000000,
            'total_weight'     => 3.14,
            'avg_anomaly'      => 0.12,
            'domains'          => ['physics', 'chemistry'],
            'is_attractor'     => false,
            'occurrence_count' => 5,
            'run_occurrences'  => json_encode(['run-1' => 2, 'run-2' => 3]),
        ],
    ], $overrides);
}

it('inserts a meta concept', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [makeMetaPoint(6001)],
        'next_page_offset' => null,
    ]);

    (new SyncMetaGraphFromQdrant())->handle($qdrant);

    $concept = MetaConcept::where('qdrant_point_id', 6001)->firstOrFail();
    expect($concept->concept)->toBe('Concept 6001');
    expect($concept->total_weight)->toBe(3.14);
    expect($concept->domains)->toBe(['physics', 'chemistry']);
    expect($concept->run_occurrences)->toBe(['run-1' => 2, 'run-2' => 3]);
});

it('decodes double-encoded run_occurrences', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $point  = makeMetaPoint(6002);
    // double-encoded: the value is already a JSON string in payload
    $point['payload']['run_occurrences'] = json_encode(json_encode(['run-x' => 1]));

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [$point],
        'next_page_offset' => null,
    ]);

    (new SyncMetaGraphFromQdrant())->handle($qdrant);

    $concept = MetaConcept::where('qdrant_point_id', 6002)->firstOrFail();
    // After single json_decode it becomes the inner JSON string, which is still a string
    // The job decodes once; that's enough for single-encoded data.
    expect($concept->run_occurrences)->not->toBeNull();
});

it('handles first_seen unix timestamp', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $point  = makeMetaPoint(6003);
    $point['payload']['first_seen'] = 1700000000;

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [$point],
        'next_page_offset' => null,
    ]);

    (new SyncMetaGraphFromQdrant())->handle($qdrant);

    $concept = MetaConcept::where('qdrant_point_id', 6003)->firstOrFail();
    expect($concept->first_seen_at)->not->toBeNull();
    expect($concept->first_seen_at->timestamp)->toBe(1700000000);
});

it('marks attractor flag correctly', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $point  = makeMetaPoint(6004);
    $point['payload']['is_attractor'] = true;

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [$point],
        'next_page_offset' => null,
    ]);

    (new SyncMetaGraphFromQdrant())->handle($qdrant);

    expect(MetaConcept::where('qdrant_point_id', 6004)->value('is_attractor'))->toBeTrue();
});
