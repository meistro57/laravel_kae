<?php

use App\Jobs\Sync\SyncNodesFromQdrant;
use App\Models\Node;
use App\Models\Run;
use App\Services\QdrantService;

function makeNodePoint(int $id, string $runId = 'run-nodes', array $overrides = []): array
{
    return array_merge([
        'id'      => $id,
        'payload' => [
            'run_id'  => $runId,
            'label'   => "Concept {$id}",
            'domain'  => 'physics',
            'weight'  => 0.75,
            'anomaly' => false,
            'sources' => null,
            'cycle'   => 2,
        ],
    ], $overrides);
}

it('inserts nodes with null sources gracefully', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [makeNodePoint(5001)],
        'next_page_offset' => null,
    ]);

    (new SyncNodesFromQdrant())->handle($qdrant);

    $node = Node::where('qdrant_point_id', 5001)->firstOrFail();
    expect($node->sources)->toBeNull();
    expect($node->label)->toBe('Concept 5001');
    expect(Run::where('run_id_go', 'run-nodes')->exists())->toBeTrue();
});

it('parses JSON-encoded sources string', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $point  = makeNodePoint(5002);
    $point['payload']['sources'] = json_encode([111, 222]);

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [$point],
        'next_page_offset' => null,
    ]);

    (new SyncNodesFromQdrant())->handle($qdrant);

    expect(Node::where('qdrant_point_id', 5002)->value('sources'))->toBe([111, 222]);
});

it('defaults domain to inferred when missing', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $point  = makeNodePoint(5003);
    unset($point['payload']['domain']);

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [$point],
        'next_page_offset' => null,
    ]);

    (new SyncNodesFromQdrant())->handle($qdrant);

    expect(Node::where('qdrant_point_id', 5003)->value('domain'))->toBe('inferred');
});

it('filters by runIdGo when provided', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')
        ->with(
            config('kae.collections.nodes'),
            ['must' => [['key' => 'run_id', 'match' => ['value' => 'run-filter']]]],
            100,
            null,
        )
        ->once()
        ->andReturn(['points' => [makeNodePoint(5004, 'run-filter')], 'next_page_offset' => null]);

    (new SyncNodesFromQdrant('run-filter'))->handle($qdrant);

    expect(Node::where('qdrant_point_id', 5004)->exists())->toBeTrue();
});
