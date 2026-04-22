<?php

use App\Models\AuditResult;

it('can create and read an audit result', function () {
    $audit = AuditResult::factory()->create([
        'issues_found'    => 10,
        'issues_repaired' => 7,
    ]);

    $fresh = AuditResult::find($audit->id);

    expect($fresh->issues_found)->toBe(10)
        ->and($fresh->issues_repaired)->toBe(7);
});

it('casts summary as array', function () {
    $audit = AuditResult::factory()->create([
        'summary' => ['collection' => 'kae_chunks', 'total_scanned' => 500],
    ]);

    expect($audit->fresh()->summary)->toBeArray()
        ->and($audit->fresh()->summary['collection'])->toBe('kae_chunks');
});

it('clean factory state produces zero issues', function () {
    $audit = AuditResult::factory()->clean()->create();

    expect($audit->issues_found)->toBe(0)
        ->and($audit->issues_repaired)->toBe(0)
        ->and($audit->details)->toBeArray()->toBeEmpty();
});
