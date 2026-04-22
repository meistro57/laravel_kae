<?php

use App\Models\GutenbergBlacklist;

it('can create and read a blacklist entry', function () {
    $entry = GutenbergBlacklist::factory()->create([
        'title'          => 'The Book of Enoch',
        'reason'         => 'Title-content mismatch detected in Project Gutenberg',
        'detection_date' => '2026-04-17',
        'active'         => true,
    ]);

    $fresh = GutenbergBlacklist::find($entry->id);

    expect($fresh->title)->toBe('The Book of Enoch')
        ->and($fresh->active)->toBeTrue()
        ->and($fresh->detection_date)->not->toBeNull();
});

it('active scope returns only active entries', function () {
    GutenbergBlacklist::factory()->count(2)->create(['active' => true]);
    GutenbergBlacklist::factory()->inactive()->count(1)->create();

    expect(GutenbergBlacklist::active()->count())->toBe(2);
});

it('can be deactivated', function () {
    $entry = GutenbergBlacklist::factory()->create(['active' => true]);

    $entry->update(['active' => false]);

    expect($entry->fresh()->active)->toBeFalse();
});
