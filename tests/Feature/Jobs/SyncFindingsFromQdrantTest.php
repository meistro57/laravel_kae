<?php

use App\Jobs\Sync\SyncFindingsFromQdrant;
use App\Models\Chunk;
use App\Models\Finding;
use App\Models\Run;
use App\Services\QdrantService;

function makeFindingPoint(string $id, array $overrides = []): array
{
    return array_merge([
        'id'      => $id,
        'payload' => [
            'summary'          => 'A novel finding about quantum entanglement',
            'confidence'       => 0.87,
            'source_point_ids' => [1001, 1002],
            'type'             => 'synthesis',
            'batch_id'         => 'batch-001',
            'reviewed'         => false,
            'reasoning_trace'  => 'Step 1: observe. Step 2: conclude.',
            'correction'       => null,
            'domains'          => ['physics'],
            'reasoning_model'  => 'deepseek/deepseek-r1',
            'created_at'       => 1700000000,
            'embedding_text'   => 'quantum entanglement summary',
        ],
    ], $overrides);
}

it('inserts a finding from Qdrant payload', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $uuid   = '550e8400-e29b-41d4-a716-446655440000';

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [makeFindingPoint($uuid)],
        'next_page_offset' => null,
    ]);

    (new SyncFindingsFromQdrant())->handle($qdrant);

    $finding = Finding::where('qdrant_point_id', $uuid)->firstOrFail();
    expect($finding->finding)->toBe('A novel finding about quantum entanglement');
    expect($finding->confidence)->toBe(0.87);
    expect($finding->type)->toBe('synthesis');
    expect($finding->reviewed)->toBeFalse();
    expect($finding->domains)->toBe(['physics']);
    expect($finding->sources)->toBe([1001, 1002]);
    expect($finding->raw_payload)->toHaveKey('embedding_text');
});

it('maps summary field to finding column', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $uuid   = '550e8400-e29b-41d4-a716-446655440001';

    $point = makeFindingPoint($uuid);
    $point['payload']['summary'] = 'The summary text';
    unset($point['payload']['finding']);

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [$point],
        'next_page_offset' => null,
    ]);

    (new SyncFindingsFromQdrant())->handle($qdrant);

    expect(Finding::where('qdrant_point_id', $uuid)->value('finding'))->toBe('The summary text');
});

it('resolves anchor chunk from first source_point_id', function () {
    $run   = Run::factory()->create(['run_id_go' => 'run-finding']);
    $chunk = Chunk::factory()->create(['qdrant_point_id' => 9001, 'run_id' => $run->id]);
    $uuid  = '550e8400-e29b-41d4-a716-446655440002';

    $qdrant = Mockery::mock(QdrantService::class);
    $point  = makeFindingPoint($uuid);
    $point['payload']['source_point_ids'] = [9001, 9002];

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [$point],
        'next_page_offset' => null,
    ]);

    (new SyncFindingsFromQdrant())->handle($qdrant);

    $finding = Finding::where('qdrant_point_id', $uuid)->firstOrFail();
    expect((string) $finding->anchor_chunk_id)->toBe((string) $chunk->id);
    expect((string) $finding->run_id)->toBe((string) $run->id);
});

it('stores embedding_text in raw_payload and not elsewhere', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $uuid   = '550e8400-e29b-41d4-a716-446655440003';

    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [makeFindingPoint($uuid)],
        'next_page_offset' => null,
    ]);

    (new SyncFindingsFromQdrant())->handle($qdrant);

    $finding = Finding::where('qdrant_point_id', $uuid)->firstOrFail();
    expect($finding->raw_payload['embedding_text'])->toBe('quantum entanglement summary');
});
