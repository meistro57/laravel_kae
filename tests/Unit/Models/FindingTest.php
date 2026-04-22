<?php

use App\Models\Chunk;
use App\Models\Finding;
use App\Models\Run;
use Illuminate\Support\Str;

it('can create and read a finding', function () {
    $finding = Finding::factory()->create([
        'finding'           => 'Novel insight: consciousness correlates with integrated complexity.',
        'confidence'        => 0.88,
        'density_assessment'=> 'dense',
        'reasoning_model'   => 'deepseek/deepseek-r1',
    ]);

    $fresh = Finding::find($finding->id);

    expect($fresh->finding)->toContain('consciousness')
        ->and($fresh->confidence)->toBe(0.88)
        ->and($fresh->density_assessment)->toBe('dense')
        ->and($fresh->reasoning_model)->toBe('deepseek/deepseek-r1');
});

it('has a uuid primary key', function () {
    $finding = Finding::factory()->create();

    expect(Str::isUuid($finding->id))->toBeTrue();
});

it('qdrant_point_id stores as string (uuid format)', function () {
    $uuid    = (string) Str::uuid();
    $finding = Finding::factory()->create(['qdrant_point_id' => $uuid]);

    expect($finding->fresh()->qdrant_point_id)->toBe($uuid);
});

it('belongs to a run', function () {
    $run     = Run::factory()->create();
    $finding = Finding::factory()->create(['run_id' => $run->id]);

    expect($finding->run->id)->toBe($run->id);
});

it('belongs to an anchor chunk', function () {
    $chunk   = Chunk::factory()->create();
    $finding = Finding::factory()->create(['anchor_chunk_id' => $chunk->id]);

    expect($finding->anchorChunk->id)->toBe($chunk->id);
});

it('highConfidence scope filters correctly', function () {
    Finding::factory()->count(3)->create(['confidence' => 0.60]);
    Finding::factory()->highConfidence()->count(2)->create();

    expect(Finding::highConfidence(0.75)->count())->toBe(2);
});

it('can be created without run_id or anchor_chunk_id', function () {
    $finding = Finding::factory()->create([
        'run_id'          => null,
        'anchor_chunk_id' => null,
    ]);

    expect($finding->run_id)->toBeNull()
        ->and($finding->anchor_chunk_id)->toBeNull();
});
