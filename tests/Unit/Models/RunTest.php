<?php

use App\Data\RunSettings;
use App\Models\Chunk;
use App\Models\Finding;
use App\Models\Node;
use App\Models\Run;
use Illuminate\Support\Str;

it('can create and read a run', function () {
    $run = Run::factory()->create([
        'seed'      => 'consciousness and emergence',
        'status'    => 'pending',
        'run_id_go' => 'run_20260420_abc123',
    ]);

    $fresh = Run::find($run->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->seed)->toBe('consciousness and emergence')
        ->and($fresh->status)->toBe('pending')
        ->and($fresh->run_id_go)->toBe('run_20260420_abc123');
});

it('casts settings to a RunSettings DTO', function () {
    $run = Run::factory()->create([
        'settings' => RunSettings::from([
            'model'      => 'claude-opus-4-6',
            'max_cycles' => 5,
        ]),
    ]);

    $fresh = Run::find($run->id);

    expect($fresh->settings)->toBeInstanceOf(RunSettings::class)
        ->and($fresh->settings->model)->toBe('claude-opus-4-6')
        ->and($fresh->settings->max_cycles)->toBe(5)
        ->and($fresh->settings->stagnation_window)->toBe(3);
});

it('has uuid primary key', function () {
    $run = Run::factory()->create();

    expect(Str::isUuid($run->id))->toBeTrue();
});

it('has many chunks through run_id relationship', function () {
    $run = Run::factory()->create();
    Chunk::factory()->count(3)->create(['run_id' => $run->id]);

    expect($run->chunks)->toHaveCount(3);
});

it('has many nodes through run_id relationship', function () {
    $run = Run::factory()->create();
    Node::factory()->count(2)->create(['run_id' => $run->id]);

    expect($run->nodes)->toHaveCount(2);
});

it('has many findings through run_id relationship', function () {
    $run = Run::factory()->create();
    Finding::factory()->count(4)->create(['run_id' => $run->id]);

    expect($run->findings)->toHaveCount(4);
});

it('scopes filter by status correctly', function () {
    Run::factory()->count(2)->create(['status' => 'completed']);
    Run::factory()->count(1)->create(['status' => 'running']);
    Run::factory()->count(1)->create(['status' => 'failed']);

    expect(Run::completed()->count())->toBe(2)
        ->and(Run::running()->count())->toBe(1)
        ->and(Run::pending()->count())->toBe(0);
});

it('can update status and timestamps', function () {
    $run = Run::factory()->create(['status' => 'pending']);

    $run->update([
        'status'     => 'running',
        'started_at' => now(),
    ]);

    expect($run->fresh()->status)->toBe('running')
        ->and($run->fresh()->started_at)->not->toBeNull();
});
