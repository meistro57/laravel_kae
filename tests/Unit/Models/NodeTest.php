<?php

use App\Models\Node;
use App\Models\Run;

it('can create and read a node', function () {
    $node = Node::factory()->create([
        'label'   => 'emergent complexity',
        'domain'  => 'Philosophy',
        'weight'  => 2.45,
        'anomaly' => false,
        'cycle'   => 3,
    ]);

    $fresh = Node::find($node->id);

    expect($fresh->label)->toBe('emergent complexity')
        ->and($fresh->domain)->toBe('Philosophy')
        ->and($fresh->weight)->toBeFloat()
        ->and($fresh->anomaly)->toBeFalse()
        ->and($fresh->cycle)->toBe(3);
});

it('casts sources as array', function () {
    $node = Node::factory()->create([
        'sources' => ['https://arxiv.org/abs/2301.12345'],
    ]);

    expect($node->fresh()->sources)->toBeArray()
        ->and($node->fresh()->sources[0])->toContain('arxiv.org');
});

it('belongs to a run', function () {
    $run  = Run::factory()->create();
    $node = Node::factory()->create(['run_id' => $run->id]);

    expect($node->run->id)->toBe($run->id);
});

it('anomalous scope returns only anomaly nodes', function () {
    Node::factory()->count(3)->create(['anomaly' => false]);
    Node::factory()->anomalous()->count(2)->create();

    expect(Node::anomalous()->count())->toBe(2);
});

it('byWeight scope orders correctly', function () {
    Node::factory()->create(['weight' => 1.0]);
    Node::factory()->create(['weight' => 5.0]);
    Node::factory()->create(['weight' => 3.0]);

    $weights = Node::byWeight()->pluck('weight')->toArray();

    expect($weights[0])->toBe(5.0)
        ->and($weights[2])->toBe(1.0);
});
