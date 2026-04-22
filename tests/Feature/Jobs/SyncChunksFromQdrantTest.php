<?php

use App\Jobs\Sync\SyncChunksFromQdrant;
use App\Models\Chunk;
use App\Models\Run;
use App\Models\SyncCursor;
use App\Services\QdrantService;

function makeChunkPoint(int $id, string $runId = 'run-abc', array $overrides = []): array
{
    return array_merge([
        'id'      => $id,
        'payload' => [
            'run_id'            => $runId,
            'text'              => "Chunk text for {$id}",
            'source'            => 'arxiv',
            'run_topic'         => 'test topic',
            'semantic_domain'   => 'science',
            'domain_confidence' => 0.92,
            'lens_processed'    => false,
            'lens_correction'   => false,
        ],
    ], $overrides);
}

it('inserts new chunks from a single Qdrant page', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')
        ->once()
        ->andReturn([
            'points'           => [makeChunkPoint(1001), makeChunkPoint(1002)],
            'next_page_offset' => null,
        ]);

    (new SyncChunksFromQdrant())->handle($qdrant);

    expect(Chunk::count())->toBe(2);
    expect(Chunk::where('qdrant_point_id', 1001)->exists())->toBeTrue();
    expect(Run::where('run_id_go', 'run-abc')->exists())->toBeTrue();
});

it('updates an existing chunk on re-sync', function () {
    $run = Run::factory()->create(['run_id_go' => 'run-xyz']);
    Chunk::factory()->create(['qdrant_point_id' => 2001, 'run_id' => $run->id, 'text' => 'old text']);

    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [makeChunkPoint(2001, 'run-xyz', ['payload' => [
            'run_id' => 'run-xyz', 'text' => 'new text', 'source' => 'wiki',
            'run_topic' => '', 'semantic_domain' => 'history',
            'domain_confidence' => 0.5, 'lens_processed' => false, 'lens_correction' => false,
        ]])],
        'next_page_offset' => null,
    ]);

    (new SyncChunksFromQdrant())->handle($qdrant);

    expect(Chunk::where('qdrant_point_id', 2001)->value('text'))->toBe('new text');
    expect(Chunk::count())->toBe(1);
});

it('does not overwrite lens_correction=true chunks source run link', function () {
    $run = Run::factory()->create(['run_id_go' => 'run-lc']);
    Chunk::factory()->create([
        'qdrant_point_id' => 3001,
        'run_id'          => $run->id,
        'lens_correction' => true,
        'lens_processed'  => true,
        'text'            => 'correction chunk',
    ]);

    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')->once()->andReturn([
        'points'           => [makeChunkPoint(3001, 'run-lc', ['payload' => [
            'run_id' => 'run-lc', 'text' => 'correction chunk', 'source' => 'lens',
            'run_topic' => '', 'semantic_domain' => 'meta',
            'domain_confidence' => 1.0, 'lens_processed' => true, 'lens_correction' => true,
        ]])],
        'next_page_offset' => null,
    ]);

    (new SyncChunksFromQdrant())->handle($qdrant);

    expect(Chunk::where('qdrant_point_id', 3001)->value('lens_correction'))->toBeTrue();
});

it('paginates across multiple Qdrant pages', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')
        ->twice()
        ->andReturn(
            ['points' => [makeChunkPoint(4001), makeChunkPoint(4002)], 'next_page_offset' => 4002],
            ['points' => [makeChunkPoint(4003)], 'next_page_offset' => null],
        );

    (new SyncChunksFromQdrant())->handle($qdrant);

    expect(Chunk::count())->toBe(3);
});

it('stops cleanly when Qdrant returns no points', function () {
    $qdrant = Mockery::mock(QdrantService::class);
    $qdrant->shouldReceive('scroll')->once()->andReturn(['points' => [], 'next_page_offset' => null]);

    (new SyncChunksFromQdrant())->handle($qdrant);

    expect(Chunk::count())->toBe(0);
    expect(SyncCursor::forCollection(config('kae.collections.chunks'))->last_point_id)->toBeNull();
});
