<?php

use App\Models\MetaConcept;

it('can create and read a meta concept', function () {
    $concept = MetaConcept::factory()->create([
        'concept'          => 'strange attractor unique-xyz',
        'occurrence_count' => 5,
        'is_attractor'     => true,
        'total_weight'     => 12.5,
    ]);

    $fresh = MetaConcept::find($concept->id);

    expect($fresh->concept)->toBe('strange attractor unique-xyz')
        ->and($fresh->occurrence_count)->toBe(5)
        ->and($fresh->is_attractor)->toBeTrue()
        ->and($fresh->total_weight)->toBeFloat();
});

it('casts domains as array', function () {
    $concept = MetaConcept::factory()->create([
        'domains' => ['Philosophy', 'Neuroscience'],
    ]);

    expect($concept->fresh()->domains)->toBeArray()
        ->and($concept->fresh()->domains)->toContain('Philosophy');
});

it('casts run_occurrences as array', function () {
    $concept = MetaConcept::factory()->create([
        'run_occurrences' => [
            ['run_id' => 'run_abc', 'cycle' => 2, 'weight' => 1.5, 'anomaly' => false],
        ],
    ]);

    expect($concept->fresh()->run_occurrences)->toBeArray()
        ->and($concept->fresh()->run_occurrences[0]['run_id'])->toBe('run_abc');
});

it('attractors scope returns only is_attractor=true concepts', function () {
    MetaConcept::factory()->count(3)->create(['is_attractor' => false]);
    MetaConcept::factory()->attractor()->count(2)->create();

    expect(MetaConcept::attractors()->count())->toBe(2);
});

it('enforces unique concept string', function () {
    MetaConcept::factory()->create(['concept' => 'unique-concept-guard-test']);

    expect(fn () => MetaConcept::factory()->create(['concept' => 'unique-concept-guard-test']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
