<?php

use App\Models\Chunk;
use App\Models\Run;

it('can create and read a chunk', function () {
    $chunk = Chunk::factory()->create([
        'text'              => 'Consciousness may arise from integrated information.',
        'semantic_domain'   => 'Neuroscience',
        'domain_confidence' => 0.92,
    ]);

    $fresh = Chunk::find($chunk->id);

    expect($fresh->text)->toBe('Consciousness may arise from integrated information.')
        ->and($fresh->semantic_domain)->toBe('Neuroscience')
        ->and($fresh->domain_confidence)->toBeFloat()
        ->and($fresh->lens_processed)->toBeFalse()
        ->and($fresh->lens_correction)->toBeFalse();
});

it('enforces unique qdrant_point_id', function () {
    Chunk::factory()->create(['qdrant_point_id' => 1234567]);

    expect(fn () => Chunk::factory()->create(['qdrant_point_id' => 1234567]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('belongs to a run', function () {
    $run   = Run::factory()->create();
    $chunk = Chunk::factory()->create(['run_id' => $run->id]);

    expect($chunk->run->id)->toBe($run->id);
});

it('can be created without a run_id', function () {
    $chunk = Chunk::factory()->create(['run_id' => null]);

    expect($chunk->run_id)->toBeNull();
});

it('unprocessed scope excludes lens_processed and lens_correction chunks', function () {
    Chunk::factory()->create(['lens_processed' => false, 'lens_correction' => false]);
    Chunk::factory()->processed()->create();
    Chunk::factory()->correction()->create();

    expect(Chunk::unprocessed()->count())->toBe(1);
});
